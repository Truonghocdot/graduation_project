<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('driver_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('client_message_id');
            $table->string('message_type', 20);
            $table->text('body')->nullable();
            $table->text('attachment_path')->nullable();
            $table->timestampTz('sent_at');
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at');
            $table->unique(
                ['chat_conversation_id', 'client_message_id'],
                'chat_messages_conversation_client_unique'
            );
            $table->index(['chat_conversation_id', 'id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 100)->index();
            $table->string('channel', 20);
            $table->jsonb('data');
            $table->string('status', 20)->default('PENDING');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
