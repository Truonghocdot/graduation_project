<?php

namespace App\Services\Booking;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QuoteStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\ServiceStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceRequestCancellationService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly WalletPaymentService $walletPayment,
        private readonly VoucherRedemptionService $voucherRedemption,
    ) {}

    /** @param array<string, mixed> $data */
    public function cancel(
        User $user,
        ServiceRequest $serviceRequest,
        array $data,
        string $idempotencyKey,
    ): ServiceRequest {
        return DB::transaction(function () use ($user, $serviceRequest, $data, $idempotencyKey): ServiceRequest {
            $idempotency = $this->idempotency->begin(
                $user,
                'service-request.cancel',
                $idempotencyKey,
                ['service_request_id' => $serviceRequest->public_id, ...$data],
            );

            if ($idempotency->status === 'COMPLETED') {
                return ServiceRequest::query()->findOrFail($idempotency->resource_id)->load([
                    'quote', 'vehicleType', 'stops', 'deliveryOrder', 'rideBooking', 'payment',
                ]);
            }

            $request = ServiceRequest::query()
                ->whereKey($serviceRequest->id)
                ->where('created_by', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($request->status, [
                ServiceRequestStatus::Scheduled,
                ServiceRequestStatus::SearchingDriver,
            ], true)) {
                throw ValidationException::withMessages([
                    'service_request' => ['Không thể hủy yêu cầu dịch vụ ở trạng thái hiện tại.'],
                ]);
            }

            if (DB::table('assignments')->where('service_request_id', $request->id)->where('status', 'ACTIVE')->exists()) {
                throw ValidationException::withMessages([
                    'service_request' => ['Yêu cầu dịch vụ đã có chuyến được phân công đang hoạt động.'],
                ]);
            }

            $payment = Payment::query()->where('service_request_id', $request->id)->lockForUpdate()->firstOrFail();
            if ($payment->method === PaymentMethod::Wallet) {
                $this->walletPayment->refund(
                    $payment,
                    (int) $payment->payer_user_id,
                    $payment->customer_payable,
                    $idempotencyKey,
                );
            }

            $this->voucherRedemption->restore($payment, (string) $data['reason_code']);
            $fromStatus = $request->status;
            $request->forceFill([
                'status' => ServiceRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason_code' => $data['reason_code'],
                'version' => $request->version + 1,
            ])->save();
            $payment->forceFill(['status' => PaymentStatus::Cancelled, 'version' => $payment->version + 1])->save();
            $request->quote()->update(['status' => QuoteStatus::Cancelled]);

            ServiceStatusHistory::query()->create([
                'service_request_id' => $request->id,
                'version' => $request->version,
                'from_status' => $fromStatus->value,
                'to_status' => ServiceRequestStatus::Cancelled->value,
                'actor_user_id' => $user->id,
                'actor_type' => 'CUSTOMER',
                'reason_code' => $data['reason_code'],
                'metadata' => [],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);
            OutboxEvent::query()->create([
                'event_type' => 'SERVICE_REQUEST_CANCELLED',
                'aggregate_type' => 'SERVICE_REQUEST',
                'aggregate_id' => $request->id,
                'aggregate_version' => $request->version,
                'payload' => [
                    'service_request_id' => $request->public_id,
                    'service_type' => $request->service_type->value,
                    'status' => ServiceRequestStatus::Cancelled->value,
                ],
                'status' => 'PENDING',
                'attempt_count' => 0,
                'available_at' => now(),
            ]);

            $this->idempotency->complete(
                $idempotency,
                200,
                ['id' => $request->public_id],
                ServiceRequest::class,
                $request->id,
            );

            return $request->load([
                'quote', 'vehicleType', 'stops', 'deliveryOrder', 'rideBooking', 'payment',
            ]);
        });
    }
}
