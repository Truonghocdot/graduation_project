<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('batch_number');
            $table->string('status', 20)->default('PENDING');
            $table->double('estimated_pickup_distance_meters');
            $table->unsignedInteger('estimated_pickup_seconds');
            $table->double('estimated_driver_earning');
            $table->timestampTz('offered_at');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['service_request_id', 'driver_profile_id', 'batch_number'],
                'driver_offers_request_driver_batch_unique'
            );
            $table->index(['driver_profile_id', 'status', 'expires_at']);
            $table->index(['service_request_id', 'batch_number', 'status'], 'driver_offers_batch_lookup');
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('accepted_offer_id')->nullable()->unique()->constrained('driver_offers')->nullOnDelete();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('assigned_at');
            $table->timestampTz('closed_at')->nullable();
            $table->string('close_reason_code', 50)->nullable();
            $table->timestampsTz();
        });

        Schema::create('service_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 20);
            $table->string('reason_code', 50)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->uuid('correlation_id')->index();
            $table->timestampTz('created_at');
            $table->unique(['service_request_id', 'version']);
        });

        Schema::create('delivery_return_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')
                ->constrained('delivery_orders', 'service_request_id')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->string('reason_code', 50);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->double('return_fare');
            $table->double('driver_rate');
            $table->double('driver_earning');
            $table->jsonb('pricing_snapshot');
            $table->jsonb('proof')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('returned_at')->nullable();
            $table->timestampsTz();
            $table->unique(['delivery_order_id', 'revision_number'], 'delivery_return_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_return_revisions');
        Schema::dropIfExists('service_status_histories');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('driver_offers');
    }
};
