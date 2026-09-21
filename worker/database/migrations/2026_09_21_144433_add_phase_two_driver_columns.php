<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->timestampTz('submitted_at')->nullable()->after('reviewed_at');
            $table->string('review_status', 30)->default('DRAFT')->change();
        });

        Schema::table('driver_documents', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->unique()->after('id');
        });

        Schema::table('driver_bank_accounts', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });

        Schema::table('driver_documents', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->string('review_status', 30)->default('PENDING_REVIEW')->change();
            $table->dropColumn('submitted_at');
        });
    }
};
