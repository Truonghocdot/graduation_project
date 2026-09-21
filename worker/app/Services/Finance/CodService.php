<?php

namespace App\Services\Finance;

use App\Models\Assignment;
use App\Models\CodAccount;
use App\Models\CodTransaction;
use App\Models\DeliveryOrder;
use App\Models\ServiceEvidence;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CodService
{
    public function advance(
        DeliveryOrder $delivery,
        Assignment $assignment,
        User $driver,
        ServiceEvidence $evidence,
        string $idempotencyKey,
    ): ?CodAccount {
        if (! $delivery->is_cod || $delivery->cod_amount <= 0) {
            return null;
        }

        if ($delivery->cod_amount > $assignment->driverProfile->cod_limit) {
            throw ValidationException::withMessages([
                'cod_amount' => ['The COD amount exceeds the driver limit.'],
            ]);
        }

        $account = CodAccount::query()->firstOrCreate(
            ['delivery_order_id' => $delivery->service_request_id],
            [
                'driver_profile_id' => $assignment->driver_profile_id,
                'cod_amount' => $delivery->cod_amount,
                'status' => 'PENDING_ADVANCE',
                'advanced_amount' => 0,
                'collected_amount' => 0,
            ],
        );
        $existing = CodTransaction::query()
            ->where('idempotency_key', 'cod:advance:'.$idempotencyKey)
            ->exists();

        if ($existing) {
            return $account;
        }

        CodTransaction::query()->create([
            'cod_account_id' => $account->id,
            'transaction_type' => 'ADVANCE_TO_SENDER',
            'amount' => $delivery->cod_amount,
            'actor_user_id' => $driver->id,
            'evidence' => ['service_evidence_id' => $evidence->public_id],
            'idempotency_key' => 'cod:advance:'.$idempotencyKey,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
        $account->forceFill([
            'status' => 'ADVANCED',
            'advanced_amount' => $delivery->cod_amount,
            'advanced_at' => now(),
        ])->save();

        return $account;
    }

    public function collect(
        DeliveryOrder $delivery,
        Assignment $assignment,
        User $driver,
        ServiceEvidence $evidence,
        float $collectedAmount,
        string $idempotencyKey,
    ): ?CodAccount {
        if (! $delivery->is_cod || $delivery->cod_amount <= 0) {
            return null;
        }

        if (abs($collectedAmount - $delivery->cod_amount) > 0.01) {
            throw ValidationException::withMessages([
                'cod_collected' => ['Collected COD must match the advanced COD amount.'],
            ]);
        }

        $account = CodAccount::query()
            ->where('delivery_order_id', $delivery->service_request_id)
            ->lockForUpdate()
            ->first();

        if ($account === null || $account->status !== 'ADVANCED') {
            throw ValidationException::withMessages([
                'cod_collected' => ['COD must be advanced before it can be collected.'],
            ]);
        }

        if (CodTransaction::query()->where('idempotency_key', 'cod:collect:'.$idempotencyKey)->exists()) {
            return $account;
        }

        CodTransaction::query()->create([
            'cod_account_id' => $account->id,
            'transaction_type' => 'COLLECT_FROM_RECIPIENT',
            'amount' => $collectedAmount,
            'actor_user_id' => $driver->id,
            'evidence' => ['service_evidence_id' => $evidence->public_id],
            'idempotency_key' => 'cod:collect:'.$idempotencyKey,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
        $account->forceFill([
            'status' => 'CLOSED',
            'collected_amount' => $collectedAmount,
            'collected_at' => now(),
            'closed_at' => now(),
        ])->save();

        return $account;
    }
}
