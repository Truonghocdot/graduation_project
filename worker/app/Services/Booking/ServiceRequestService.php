<?php

namespace App\Services\Booking;

use App\Enums\BookingType;
use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QuoteStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Enums\StopType;
use App\Models\DeliveryOrder;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RideBooking;
use App\Models\ServiceRequest;
use App\Models\ServiceStatusHistory;
use App\Models\ServiceStop;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\Matching\DriverMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceRequestService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly WalletPaymentService $walletPayment,
        private readonly VoucherRedemptionService $voucherRedemption,
        private readonly DriverMatchingService $matching,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDelivery(User $user, array $data, string $idempotencyKey): ServiceRequest
    {
        return $this->create($user, $data, $idempotencyKey, ServiceType::Delivery);
    }

    /** @param array<string, mixed> $data */
    public function createRide(User $user, array $data, string $idempotencyKey): ServiceRequest
    {
        return $this->create($user, $data, $idempotencyKey, ServiceType::Drive);
    }

    /** @param array<string, mixed> $data */
    private function create(
        User $user,
        array $data,
        string $idempotencyKey,
        ServiceType $serviceType,
    ): ServiceRequest {
        $request = DB::transaction(function () use ($user, $data, $idempotencyKey, $serviceType): ServiceRequest {
            $idempotency = $this->idempotency->begin(
                $user,
                'service-request.create.'.$serviceType->value,
                $idempotencyKey,
                $data,
            );

            if ($idempotency->status === 'COMPLETED') {
                $replayedRequest = $this->load(
                    ServiceRequest::query()->findOrFail($idempotency->resource_id),
                );
                $replayedRequest->wasRecentlyCreated = true;

                return $replayedRequest;
            }

            $quote = Quote::query()
                ->where('public_id', $data['quote_id'])
                ->where('requested_by', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertQuote($quote, $serviceType);
            $payer = $this->resolvePayer($user, $data, $serviceType);
            $paymentMethod = PaymentMethod::from((string) $data['payment_method']);

            if ($serviceType === ServiceType::Delivery
                && $data['payer_type'] === PayerType::Recipient->value
                && $paymentMethod === PaymentMethod::Wallet) {
                throw ValidationException::withMessages([
                    'payment_method' => ['Đơn giao hàng do người nhận thanh toán hiện chỉ hỗ trợ tiền mặt.'],
                ]);
            }

            $vehicleType = VehicleType::query()->findOrFail($quote->vehicle_type_id);
            $bookingType = $quote->booking_type;
            $status = $bookingType === BookingType::Scheduled
                ? ServiceRequestStatus::Scheduled
                : ServiceRequestStatus::SearchingDriver;
            $now = now();

            $serviceRequest = ServiceRequest::query()->create([
                'service_type' => $serviceType,
                'created_by' => $user->id,
                'vehicle_type_id' => $vehicleType->id,
                'quote_id' => $quote->id,
                'status' => $status,
                'booking_type' => $bookingType,
                'scheduled_at' => $quote->scheduled_at,
                'search_started_at' => $status === ServiceRequestStatus::SearchingDriver ? $now : null,
                'search_attempt' => 0,
                'version' => 1,
            ]);

            $this->createStops($serviceRequest, $quote, $data);
            $payment = Payment::query()->create([
                'service_request_id' => $serviceRequest->id,
                'payer_type' => $serviceType === ServiceType::Drive
                    ? PayerType::Orderer
                    : PayerType::from((string) $data['payer_type']),
                'payer_user_id' => $payer->id,
                'method' => $paymentMethod,
                'status' => PaymentStatus::Ready,
                'currency' => $quote->currency,
                'gross_fare' => $quote->gross_fare,
                'voucher_discount' => $quote->voucher_discount,
                'customer_payable' => $quote->customer_payable,
                'cash_collected' => 0,
                'version' => 1,
            ]);

            $this->voucherRedemption->redeem($user, $quote, $serviceRequest, $payment);

            if ($paymentMethod === PaymentMethod::Wallet) {
                $this->walletPayment->debit(
                    $payment,
                    $payer->id,
                    $quote->customer_payable,
                    $idempotencyKey,
                );
            }

            if ($serviceType === ServiceType::Delivery) {
                $this->createDeliveryOrder($serviceRequest, $quote, $data, $payer);
            } else {
                $this->createRideBooking($serviceRequest, $quote);
            }

            $quote->forceFill([
                'status' => QuoteStatus::Used,
                'used_at' => $now,
            ])->save();
            $this->recordStatus($serviceRequest, null, $status, $user->id, [
                'payment_method' => $paymentMethod->value,
                'payer_user_id' => $payer->id,
            ]);
            $this->outbox($serviceRequest, match ($serviceType) {
                ServiceType::Delivery => 'DELIVERY_ORDER_CREATED',
                ServiceType::Drive => 'RIDE_BOOKING_CREATED',
            });

            if ($status === ServiceRequestStatus::SearchingDriver) {
                $this->outbox($serviceRequest, match ($serviceType) {
                    ServiceType::Delivery => 'DELIVERY_SEARCH_REQUESTED',
                    ServiceType::Drive => 'RIDE_SEARCH_REQUESTED',
                });
            }

            $this->idempotency->complete(
                $idempotency,
                201,
                ['id' => $serviceRequest->public_id],
                ServiceRequest::class,
                $serviceRequest->id,
            );

            return $this->load($serviceRequest);
        });

        // Matching is best-effort after the booking is committed. A Redis or
        // presence failure must not undo a paid booking; the scheduler retries.
        if ($request->status === ServiceRequestStatus::SearchingDriver) {
            try {
                $this->matching->dispatch($request);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $request->refresh();
        $request->wasRecentlyCreated = true;

        return $this->load($request);
    }

    private function assertQuote(Quote $quote, ServiceType $serviceType): void
    {
        if ($quote->service_type !== $serviceType) {
            throw ValidationException::withMessages([
                'quote_id' => ['Báo giá không khớp với dịch vụ này.'],
            ]);
        }

        if ($quote->status !== QuoteStatus::Active) {
            throw ValidationException::withMessages([
                'quote_id' => ['Báo giá đã được sử dụng hoặc đã bị hủy.'],
            ]);
        }

        if ($quote->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'quote_id' => ['Báo giá đã hết hạn.'],
            ]);
        }

        if ($quote->booking_type === BookingType::Scheduled && $quote->scheduled_at?->isPast()) {
            throw ValidationException::withMessages([
                'quote_id' => ['Thời gian đã đặt đã qua.'],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function resolvePayer(User $user, array $data, ServiceType $serviceType): User
    {
        if ($serviceType === ServiceType::Drive || $data['payer_type'] === PayerType::Orderer->value) {
            return $user;
        }

        $payer = User::query()->where('public_id', $data['recipient_user_id'])->first();

        if ($payer === null) {
            throw ValidationException::withMessages([
                'recipient_user_id' => ['Không tìm thấy người nhận thanh toán.'],
            ]);
        }

        return $payer;
    }

    /** @param array<string, mixed> $data */
    private function createStops(ServiceRequest $request, Quote $quote, array $data): void
    {
        foreach ([StopType::Pickup, StopType::Dropoff] as $stopType) {
            $snapshot = $stopType === StopType::Pickup
                ? $quote->pickup_snapshot
                : $quote->dropoff_snapshot;
            $input = data_get($data, 'stops.'.mb_strtolower($stopType->value), []);

            ServiceStop::query()->create([
                'service_request_id' => $request->id,
                'stop_type' => $stopType,
                'address' => $snapshot['address'],
                'latitude' => $snapshot['latitude'],
                'longitude' => $snapshot['longitude'],
                'contact_name' => $input['contact_name'] ?? null,
                'contact_phone' => $input['contact_phone'] ?? null,
                'note' => $input['note'] ?? ($snapshot['note'] ?? null),
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function createDeliveryOrder(
        ServiceRequest $request,
        Quote $quote,
        array $data,
        User $payer,
    ): void {
        $payload = $quote->service_payload;
        $recipientId = isset($data['recipient_user_id'])
            ? User::query()->where('public_id', $data['recipient_user_id'])->value('id')
            : null;

        DeliveryOrder::query()->create([
            'service_request_id' => $request->id,
            'sender_user_id' => $request->created_by,
            'recipient_user_id' => $recipientId,
            'payer_type' => PayerType::from((string) $data['payer_type']),
            'goods_type' => (string) ($payload['goods_type'] ?? 'GENERAL'),
            'goods_description' => $payload['goods_description'] ?? null,
            'weight_kg' => $payload['weight_kg'] ?? null,
            'length_cm' => $payload['length_cm'] ?? null,
            'width_cm' => $payload['width_cm'] ?? null,
            'height_cm' => $payload['height_cm'] ?? null,
            'declared_value' => $payload['declared_value'] ?? 0,
            'is_cod' => (bool) ($payload['is_cod'] ?? false),
            'cod_amount' => $payload['cod_amount'] ?? 0,
            'list_type' => 'ORIGINAL',
            'proof_policy' => [],
        ]);
    }

    private function createRideBooking(ServiceRequest $request, Quote $quote): void
    {
        RideBooking::query()->create([
            'service_request_id' => $request->id,
            'passenger_count' => (int) ($quote->service_payload['passenger_count'] ?? 1),
            'route_version' => 1,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function recordStatus(
        ServiceRequest $request,
        ?ServiceRequestStatus $from,
        ServiceRequestStatus $to,
        int $actorUserId,
        array $metadata = [],
        ?string $reasonCode = null,
    ): void {
        ServiceStatusHistory::query()->create([
            'service_request_id' => $request->id,
            'version' => $request->version,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actorUserId,
            'actor_type' => 'CUSTOMER',
            'reason_code' => $reasonCode,
            'metadata' => $metadata,
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    private function outbox(ServiceRequest $request, string $eventType): void
    {
        OutboxEvent::query()->create([
            'event_type' => $eventType,
            'aggregate_type' => 'SERVICE_REQUEST',
            'aggregate_id' => $request->id,
            'aggregate_version' => $request->version,
            'payload' => [
                'service_request_id' => $request->public_id,
                'service_type' => $request->service_type->value,
                'status' => $request->status->value,
            ],
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);
    }

    private function load(ServiceRequest $request): ServiceRequest
    {
        return $request->load([
            'quote',
            'vehicleType',
            'stops',
            'deliveryOrder',
            'rideBooking',
            'payment.discountTransaction.voucherRedemption',
        ]);
    }
}
