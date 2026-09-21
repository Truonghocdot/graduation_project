<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('document_number', 100)->nullable()->index();
            $table->text('file_path');
            $table->date('expires_at')->nullable()->index();
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->index(['driver_profile_id', 'document_type', 'status']);
        });

        Schema::create('driver_service_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->string('service_type', 20);
            $table->boolean('is_active')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->unique(['driver_profile_id', 'vehicle_type_id', 'service_type'], 'driver_capability_unique');
        });

        Schema::create('driver_last_locations', function (Blueprint $table) {
            $table->foreignId('driver_profile_id')->primary()->constrained()->cascadeOnDelete();
            $table->jsonb('last_location');
            $table->timestampTz('last_location_at')->index();
            $table->timestampTz('updated_at');
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('service_type', 20);
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->double('base_distance_km')->default(3);
            $table->double('base_fare');
            $table->double('price_per_extra_km');
            $table->double('driver_rate')->default(0.88);
            $table->char('currency', 3)->default('VND');
            $table->timestampTz('effective_from')->useCurrent();
            $table->timestampTz('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['service_type', 'vehicle_type_id', 'is_active'], 'pricing_rules_lookup');
        });

        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('service_type', 20)->nullable();
            $table->jsonb('boundary');
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->jsonb('value');
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('service_areas');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('driver_last_locations');
        Schema::dropIfExists('driver_service_capabilities');
        Schema::dropIfExists('driver_documents');
    }
};
