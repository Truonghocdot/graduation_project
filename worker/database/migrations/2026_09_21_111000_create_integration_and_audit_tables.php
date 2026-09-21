<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 30);
            $table->string('action', 100)->index();
            $table->string('subject_type', 50);
            $table->unsignedBigInteger('subject_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('reason_code', 50)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('correlation_id')->index();
            $table->timestampTz('created_at')->index();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'audit_logs_subject_timeline');
        });

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 100)->index();
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedInteger('aggregate_version')->nullable();
            $table->jsonb('payload');
            $table->string('status', 20)->default('PENDING');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('consumer', 100);
            $table->uuid('message_id');
            $table->string('status', 20);
            $table->timestampTz('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['consumer', 'message_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 20);
            $table->string('actor_key', 100);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 80);
            $table->string('key', 191);
            $table->string('request_hash', 64);
            $table->string('status', 20);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->string('resource_type', 50)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
            $table->unique(
                ['actor_type', 'actor_key', 'scope', 'key'],
                'idempotency_actor_scope_key_unique'
            );
        });

        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('provider_event_id', 191);
            $table->boolean('signature_valid');
            $table->jsonb('payload');
            $table->string('status', 20);
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('audit_logs');
    }
};
