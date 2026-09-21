<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profanity_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('severity')->default(1);
            $table->timestamps();
        });

        Schema::create('profanity_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profanity_category_id')->nullable()->constrained('profanity_categories')->nullOnDelete();
            $table->string('word', 150)->unique();
            $table->string('replacement', 150)->nullable();
            $table->string('language', 10)->default('id');
            $table->unsignedTinyInteger('severity')->default(1);
            $table->boolean('is_regex')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['language', 'is_active']);
        });

        Schema::create('moderation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('trigger_type', 50)->default('keyword');
            $table->text('trigger_value')->nullable();
            $table->string('action', 30)->default('warn');
            $table->unsignedInteger('threshold')->default(1);
            $table->unsignedInteger('window_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'priority']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reportable_type', 100)->nullable();
            $table->unsignedBigInteger('reportable_id')->nullable();
            $table->string('reason', 50)->default('other');
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'created_at']);
            $table->index(['reported_user_id', 'status']);
            $table->index(['reportable_type', 'reportable_id']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 100)->nullable();
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id']);
        });

        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('moderatable_type', 100)->nullable();
            $table->unsignedBigInteger('moderatable_id')->nullable();
            $table->string('action', 30)->default('warn');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['target_user_id', 'created_at']);
            $table->index(['moderatable_type', 'moderatable_id']);
        });

        Schema::create('moderation_queue', function (Blueprint $table) {
            $table->id();
            $table->string('queueable_type', 100);
            $table->unsignedBigInteger('queueable_id');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 100)->nullable();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index(['queueable_type', 'queueable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_queue');
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('moderation_rules');
        Schema::dropIfExists('profanity_words');
        Schema::dropIfExists('profanity_categories');
    }
};
