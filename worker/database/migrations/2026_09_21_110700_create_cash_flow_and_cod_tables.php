<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->double('amount');
            $table->string('status', 20)->default('PENDING');
            $table->string('vietqr_reference', 100)->unique();
            $table->text('vietqr_payload');
            $table->string('sepay_transaction_id', 100)->nullable()->unique();
            $table->jsonb('provider_payload')->nullable();
            $table->foreignId('ledger_transaction_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->restrictOnDelete();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['wallet_id', 'status']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_bank_account_id')->constrained()->restrictOnDelete();
            $table->double('amount');
            $table->string('status', 20)->default('PENDING');
            $table->timestampTz('requested_at');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('handled_at')->nullable();
            $table->string('bank_transfer_reference', 191)->nullable()->unique();
            $table->foreignId('ledger_transaction_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->restrictOnDelete();
            $table->string('reason_code', 50)->nullable();
            $table->timestampsTz();
            $table->index(['status', 'requested_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->double('amount');
            $table->string('method', 20);
            $table->string('status', 20)->default('PENDING');
            $table->string('reason_code', 50);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ledger_transaction_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->jsonb('evidence')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['payment_id', 'status']);
        });

        Schema::create('cod_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')
                ->unique()
                ->constrained('delivery_orders', 'service_request_id')
                ->restrictOnDelete();
            $table->foreignId('driver_profile_id')->constrained()->restrictOnDelete();
            $table->double('cod_amount');
            $table->string('status', 30)->default('PENDING_ADVANCE');
            $table->double('advanced_amount')->default(0);
            $table->double('collected_amount')->default(0);
            $table->timestampTz('advanced_at')->nullable();
            $table->timestampTz('collected_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->index(['driver_profile_id', 'status']);
        });

        Schema::create('cod_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cod_account_id')->constrained()->restrictOnDelete();
            $table->string('transaction_type', 30);
            $table->double('amount');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('evidence')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_transactions');
        Schema::dropIfExists('cod_accounts');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('wallet_topups');
    }
};
