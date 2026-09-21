<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewee_user_id')->constrained('users')->restrictOnDelete();
            $table->string('direction', 30);
            $table->unsignedSmallInteger('score');
            $table->jsonb('tags')->nullable();
            $table->text('comment')->nullable();
            $table->string('moderation_status', 20)->default('VISIBLE');
            $table->timestampsTz();
            $table->unique(
                ['service_request_id', 'reviewer_user_id', 'direction'],
                'ratings_request_reviewer_direction_unique'
            );
            $table->index(['reviewee_user_id', 'created_at']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('category', 40);
            $table->string('priority', 20)->default('NORMAL');
            $table->string('status', 30)->default('OPEN');
            $table->string('subject', 191);
            $table->text('description');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_code', 50)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->index(['status', 'priority', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->string('message_type', 20);
            $table->text('body');
            $table->timestampTz('created_at')->index();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->text('storage_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64)->index();
            $table->timestampTz('created_at');
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('incident_type', 40)->index();
            $table->string('severity', 20);
            $table->string('status', 20)->default('OPEN');
            $table->text('description')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_code', 50)->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('ratings');
    }
};
