<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('type', 20)->default('text');
            $table->string('client_message_id', 64)->nullable();
            $table->timestamp('send_at');
            $table->string('status', 20)->default('pending');
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'send_at']);
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10)->default('voice');
            $table->string('status', 20)->default('ringing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('credits_charged')->default(0);
            $table->timestamps();
            $table->index(['receiver_id', 'status']);
            $table->index(['conversation_id', 'status']);
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('option_index');
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('scheduled_messages');
    }
};
