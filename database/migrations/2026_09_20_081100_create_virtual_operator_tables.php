<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('ai_personality_id')->nullable()->constrained('ai_personalities')->nullOnDelete();
            $table->string('mode', 20)->default('template');
            $table->text('persona_prompt')->nullable();
            $table->string('greeting_message', 500)->nullable();
            $table->json('reply_templates')->nullable();
            $table->unsignedInteger('reply_delay_min_seconds')->default(5);
            $table->unsignedInteger('reply_delay_max_seconds')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('virtual_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('real_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 20)->default('template');
            $table->string('status', 20)->default('active');
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'virtual_profile_id'], 'uq_virtual_conv_pair');
            $table->index(['real_user_id', 'status']);
        });

        Schema::create('chat_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('event', 80);
            $table->json('conditions')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('cooldown_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['event', 'is_active']);
        });

        Schema::create('chat_trigger_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_trigger_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 50);
            $table->json('payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['chat_trigger_id', 'sort_order']);
        });

        Schema::create('chat_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('title', 150);
            $table->text('body');
            $table->string('category', 50)->default('greeting');
            $table->string('language', 10)->default('id');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category', 'is_active']);
        });

        Schema::create('operator_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['operator_id', 'is_active']);
            $table->index(['conversation_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_assignments');
        Schema::dropIfExists('chat_templates');
        Schema::dropIfExists('chat_trigger_actions');
        Schema::dropIfExists('chat_triggers');
        Schema::dropIfExists('virtual_conversations');
        Schema::dropIfExists('virtual_profiles');
    }
};
