<?php

namespace App\Services\Finance;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LedgerService
{
    /**
     * @param  array<int, array{account: LedgerAccount, direction: 'DEBIT'|'CREDIT', amount: float, balance_after?: float|null}>  $entries
     * @param  array<string, mixed>  $metadata
     */
    public function post(
        string $transactionType,
        string $referenceType,
        int $referenceId,
        string $idempotencyKey,
        array $entries,
        array $metadata = [],
    ): LedgerTransaction {
        $existing = LedgerTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing->load('entries');
        }

        $debits = collect($entries)
            ->where('direction', 'DEBIT')
            ->sum('amount');
        $credits = collect($entries)
            ->where('direction', 'CREDIT')
            ->sum('amount');

        if (abs((float) $debits - (float) $credits) > 0.01 || (float) $debits <= 0) {
            throw new InvalidArgumentException('Ledger entries must be positive and balanced.');
        }

        $transaction = LedgerTransaction::query()->create([
            'transaction_type' => $transactionType,
            'status' => 'POSTED',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => (string) Str::uuid(),
            'metadata' => $metadata,
            'posted_at' => now(),
        ]);

        foreach ($entries as $entry) {
            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $entry['account']->id,
                'direction' => $entry['direction'],
                'amount' => $entry['amount'],
                'balance_after' => $entry['balance_after'] ?? null,
                'created_at' => now(),
            ]);
        }

        return $transaction->load('entries');
    }

    public function systemAccount(
        string $code,
        string $accountType,
        string $currency = 'VND',
    ): LedgerAccount {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => $code],
            [
                'owner_type' => 'SYSTEM',
                'owner_user_id' => null,
                'account_type' => $accountType,
                'currency' => $currency,
                'status' => 'ACTIVE',
            ],
        );
    }
}
