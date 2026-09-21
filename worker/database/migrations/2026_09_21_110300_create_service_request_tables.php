<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('service_type', 20);
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('pricing_rule_id')->constrained()->restrictOnDelete();
            $table->string('booking_type', 20);
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->jsonb('pickup_snapshot');
            $table->jsonb('dropoff_snapshot');
            $table->jsonb('service_payload');
            $table->jsonb('route_snapshot');
            $table->double('distance_meters');
            $table->unsignedInteger('duration_seconds');
            $table->double('base_fare');
            $table->double('extra_distance_fare')->default(0);
            $table->double('surcharge_amount')->default(0);
            $table->double('gross_fare');
            $table->double('voucher_discount')->default(0);
            $table->double('customer_payable');
            $table->double('driver_rate');
            $table->char('currency', 3)->default('VND');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at');
            $table->index(['requested_by', 'created_at']);
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('service_type', 20);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 40);
            $table->string('booking_type', 20);
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('search_started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason_code', 50)->nullable();
            $table->unsignedSmallInteger('search_attempt')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->index(['created_by', 'created_at']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('service_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('stop_type', 20);
            $table->text('address');
            $table->double('latitude');
            $table->double('longitude');
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();
            $table->unique(['service_request_id', 'stop_type']);
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->foreignId('service_request_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payer_type', 20);
            $table->string('goods_type', 50);
            $table->text('goods_description')->nullable();
            $table->double('weight_kg')->nullable();
            $table->double('length_cm')->nullable();
            $table->double('width_cm')->nullable();
            $table->double('height_cm')->nullable();
            $table->double('declared_value')->default(0);
            $table->boolean('is_cod')->default(false);
            $table->double('cod_amount')->default(0);
            $table->string('list_type', 20)->default('ORIGINAL');
            $table->jsonb('proof_policy')->nullable();
            $table->timestampsTz();
        });

        Schema::create('ride_bookings', function (Blueprint $table) {
            $table->foreignId('service_request_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('passenger_count')->default(1);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('route_version')->default(1);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_bookings');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('service_stops');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('quotes');
    }
};
