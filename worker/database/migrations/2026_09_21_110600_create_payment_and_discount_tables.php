<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('service_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('payer_type', 20);
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('method', 20);
            $table->string('status', 30)->default('PENDING');
            $table->char('currency', 3)->default('VND');
            $table->double('gross_fare');
            $table->double('voucher_discount')->default(0);
            $table->double('customer_payable');
            $table->foreignId('customer_payment_ledger_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->restrictOnDelete();
            $table->double('cash_collected')->default(0);
            $table->foreignId('cash_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cash_confirmed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->index(['status', 'updated_at']);
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('discount_type', 20);
            $table->double('discount_value');
            $table->double('max_discount_amount')->nullable();
            $table->string('service_scope', 20)->nullable();
            $table->double('minimum_order_amount')->default(0);
            $table->unsignedInteger('total_usage_limit')->nullable();
            $table->unsignedInteger('per_user_usage_limit')->nullable();
            $table->unsignedInteger('max_restore_count')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestampTz('starts_at')->index();
            $table->timestampTz('ends_at')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('USED');
            $table->double('discount_amount');
            $table->timestampTz('used_at');
            $table->timestampTz('restored_at')->nullable();
            $table->string('restore_reason_code', 50)->nullable();
            $table->unsignedSmallInteger('restore_count')->default(0);
            $table->timestampsTz();
            $table->index(['voucher_id', 'user_id', 'status']);
        });

        Schema::create('discount_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('voucher_redemption_id')->unique()->constrained()->restrictOnDelete();
            $table->double('amount');
            $table->string('status', 20)->default('APPLIED');
            $table->timestampTz('applied_at');
            $table->timestampTz('restored_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_profile_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('PENDING');
            $table->double('driver_rate');
            $table->double('driver_gross_earning');
            $table->double('cash_collected')->default(0);
            $table->double('wallet_payment_amount')->default(0);
            $table->double('voucher_payment_amount')->default(0);
            $table->double('platform_fee_debited')->default(0);
            $table->double('settlement_adjustment')->default(0);
            $table->double('driver_net_earning');
            $table->foreignId('earning_ledger_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->restrictOnDelete();
            $table->foreignId('platform_fee_ledger_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->restrictOnDelete();
            $table->timestampTz('settled_at')->nullable();
            $table->string('failure_code', 50)->nullable();
            $table->timestampsTz();
            $table->index(['driver_profile_id', 'status']);
        });

        Schema::create('settlement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->string('revision_type', 30);
            $table->double('gross_amount');
            $table->double('platform_fee');
            $table->double('driver_net_amount');
            $table->foreignId('ledger_transaction_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->string('reason_code', 50);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->unique(['settlement_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_revisions');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('discount_transactions');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('payments');
    }
};
