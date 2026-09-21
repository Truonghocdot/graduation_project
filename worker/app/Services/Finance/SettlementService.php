<?php

namespace App\Services\Finance;

use App\Enums\AssignmentStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\SettlementStatus;
use App\Models\Assignment;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\ServiceStatusHistory;
use App\Models\Settlement;
use App\Models\Wallet;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SettlementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PricingService $pricing,
    ) {}

    public function settle(ServiceRequest $serviceRequest): Settlement
    {
        return DB::transaction(function () use ($serviceRequest): Settlement {
            $request = ServiceRequest::query()
                ->with('quote')
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);
            $payment = Payment::query()
                ->where('service_request_id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = Settlement::query()
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === SettlementStatus::Settled) {
                return $existing->load(['payment', 'assignment', 'driverProfile.user']);
            }

            $assignment = Assignment::query()
                ->where('service_request_id', $request->id)
                ->where('status', AssignmentStatus::Active->value)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($request->status, [
                ServiceRequestStatus::Delivered,
                ServiceRequestStatus::TripEnded,
                ServiceRequestStatus::Completed,
            ], true)) {
                throw ValidationException::withMessages([
                    'settlement' => ['The service has not reached a terminal execution state.'],
                ]);
            }

            if ($payment->method === PaymentMethod::Cash
                && abs($payment->cash_collected - $payment->customer_payable) > 0.01) {
                throw ValidationException::withMessages([
                    'cash_collected' => ['The collected cash must match the customer payable amount.'],
                ]);
            }

            $driverProfile = $assignment->driverProfile;
            $driverWallet = Wallet::query()
                ->where('user_id', $driverProfile->user_id)
                ->where('currency', $payment->currency)
                ->lockForUpdate()
                ->first();

            if ($driverWallet === null) {
                throw ValidationException::withMessages([
                    'settlement' => ['The driver wallet is missing.'],
                ]);
            }

            $gross = $payment->gross_fare;
            $driverRate = $request->quote->driver_rate;
            $driverNet = $this->pricing->roundCurrency($gross * $driverRate);
            $platformFee = $this->pricing->roundCurrency($gross - $driverNet);
            $cashAmount = $payment->method === PaymentMethod::Cash
                ? $payment->customer_payable
                : 0;
            $walletAmount = $payment->method === PaymentMethod::Wallet
                ? $payment->customer_payable
                : 0;
            $voucherAmount = $payment->voucher_discount;
            $adjustment = $driverNet - (
                $cashAmount + $walletAmount + $voucherAmount - $platformFee
            );

            $settlement = $existing ?? Settlement::query()->create([
                'payment_id' => $payment->id,
                'assignment_id' => $assignment->id,
                'driver_profile_id' => $driverProfile->id,
                'status' => SettlementStatus::Processing,
                'driver_rate' => $driverRate,
                'driver_gross_earning' => $gross,
                'cash_collected' => $cashAmount,
                'wallet_payment_amount' => $walletAmount,
                'voucher_payment_amount' => $voucherAmount,
                'platform_fee_debited' => $platformFee,
                'settlement_adjustment' => $adjustment,
                'driver_net_earning' => $driverNet,
            ]);

            $earningLedgerId = null;
            if ($walletAmount > 0) {
                $driverWallet->balance += $walletAmount;
                $driverWallet->version++;
                $driverWallet->save();
                $clearing = $this->ledger->systemAccount(
                    'SYSTEM:CUSTOMER_PAYMENT:'.$payment->currency,
                    'PAYMENT_CLEARING',
                    $payment->currency,
                );
                $earningLedger = $this->ledger->post(
                    'DRIVER_EARNING',
                    'SETTLEMENT',
                    $settlement->id,
                    'settlement:'.$settlement->id.':earning',
                    [
                        ['account' => $clearing, 'direction' => 'DEBIT', 'amount' => $walletAmount],
                        [
                            'account' => $driverWallet->ledgerAccount,
                            'direction' => 'CREDIT',
                            'amount' => $walletAmount,
                            'balance_after' => $driverWallet->balance,
                        ],
                    ],
                );
                $earningLedgerId = $earningLedger->id;
            }

            $feeLedgerId = null;
            if ($platformFee > 0) {
                $driverWallet->balance -= $platformFee;
                $driverWallet->version++;
                $driverWallet->save();
                $revenue = $this->ledger->systemAccount(
                    'SYSTEM:PLATFORM_REVENUE:'.$payment->currency,
                    'REVENUE',
                    $payment->currency,
                );
                $feeLedger = $this->ledger->post(
                    'PLATFORM_FEE',
                    'SETTLEMENT',
                    $settlement->id,
                    'settlement:'.$settlement->id.':platform-fee',
                    [
                        [
                            'account' => $driverWallet->ledgerAccount,
                            'direction' => 'DEBIT',
                            'amount' => $platformFee,
                            'balance_after' => $driverWallet->balance,
                        ],
                        ['account' => $revenue, 'direction' => 'CREDIT', 'amount' => $platformFee],
                    ],
                );
                $feeLedgerId = $feeLedger->id;
            }

            $settlement->forceFill([
                'status' => SettlementStatus::Settled,
                'earning_ledger_id' => $earningLedgerId,
                'platform_fee_ledger_id' => $feeLedgerId,
                'settled_at' => now(),
                'failure_code' => null,
            ])->save();
            $payment->forceFill([
                'status' => PaymentStatus::Settled,
                'version' => $payment->version + 1,
            ])->save();
            $fromStatus = $request->status;
            $request->forceFill([
                'status' => ServiceRequestStatus::Completed,
                'completed_at' => now(),
                'version' => $request->version + 1,
            ])->save();
            $assignment->forceFill([
                'status' => AssignmentStatus::Completed,
                'closed_at' => now(),
                'close_reason_code' => 'SERVICE_COMPLETED',
            ])->save();
            $driverProfile->forceFill([
                'availability_status' => DriverAvailabilityStatus::Online,
            ])->save();
            ServiceStatusHistory::query()->create([
                'service_request_id' => $request->id,
                'version' => $request->version,
                'from_status' => $fromStatus->value,
                'to_status' => ServiceRequestStatus::Completed->value,
                'actor_user_id' => null,
                'actor_type' => 'SYSTEM',
                'metadata' => ['settlement_id' => $settlement->public_id],
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);
            $this->outbox($request, 'PAYMENT_SETTLED', [
                'payment_id' => $payment->public_id,
                'settlement_id' => $settlement->public_id,
            ]);
            $this->outbox($request, 'DRIVER_EARNING_SETTLED', [
                'settlement_id' => $settlement->public_id,
                'driver_profile_id' => $driverProfile->public_id,
            ]);

            return $settlement->load(['payment', 'assignment', 'driverProfile.user']);
        });
    }

    /** @param array<string, mixed> $payload */
    private function outbox(
        ServiceRequest $request,
        string $eventType,
        array $payload,
    ): void {
        OutboxEvent::query()->create([
            'event_type' => $eventType,
            'aggregate_type' => 'SERVICE_REQUEST',
            'aggregate_id' => $request->id,
            'aggregate_version' => $request->version,
            'payload' => ['service_request_id' => $request->public_id, ...$payload],
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);
    }
}
