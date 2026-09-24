<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('service_areas');
    }

    public function down(): void
    {
        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('service_type', 20)->nullable();
            $table->jsonb('boundary');
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });
    }
};
