<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('review_status', 30)->default('DRAFT');
            $table->string('availability_status', 20)->default('OFFLINE');
            $table->string('review_reason_code', 50)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->double('cod_limit')->default(0);
            $table->unsignedInteger('offer_count')->default(0);
            $table->unsignedInteger('accepted_offer_count')->default(0);
            $table->unsignedInteger('ignored_offer_count')->default(0);
            $table->unsignedInteger('cancelled_assignment_count')->default(0);
            $table->double('acceptance_rate')->default(0);
            $table->double('cancellation_rate')->default(0);
            $table->timestampTz('online_at')->nullable();
            $table->timestampTz('offline_at')->nullable();
            $table->timestampsTz();
            $table->index(['review_status', 'availability_status']);
        });

        Schema::create('driver_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 30);
            $table->text('account_number_encrypted');
            $table->string('account_number_hash', 64)->index();
            $table->string('account_name', 150);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('unique_key', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('passenger_capacity')->nullable();
            $table->double('max_weight_kg')->nullable();
            $table->double('max_length_cm')->nullable();
            $table->double('max_width_cm')->nullable();
            $table->double('max_height_cm')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('driver_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->string('plate_number', 30)->unique();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->boolean('is_selected')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['driver_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_types');
        Schema::dropIfExists('driver_bank_accounts');
        Schema::dropIfExists('driver_profiles');
    }
};
