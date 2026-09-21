<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('owner_type', 20);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('code', 80)->unique();
            $table->string('account_type', 30);
            $table->char('currency', 3)->default('VND');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampsTz();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->unique()->constrained()->restrictOnDelete();
            $table->char('currency', 3)->default('VND');
            $table->double('balance')->default(0);
            $table->double('reserved_withdrawal_amount')->default(0);
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->unique(['user_id', 'currency']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('transaction_type', 40)->index();
            $table->string('status', 20)->default('PENDING');
            $table->string('reference_type', 40);
            $table->unsignedBigInteger('reference_id');
            $table->string('idempotency_key', 191)->unique();
            $table->uuid('correlation_id')->index();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('direction', 10);
            $table->double('amount');
            $table->double('balance_after')->nullable();
            $table->timestampTz('created_at');
            $table->index(['ledger_account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('ledger_accounts');
    }
};
