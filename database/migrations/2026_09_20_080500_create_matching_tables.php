<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('liked_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_super')->default(false);
            $table->timestamps();
            $table->unique(['liker_id', 'liked_id']);
            $table->index(['liked_id', 'created_at']);
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->nullable()->unique();
            $table->foreignId('user_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('like_id')->nullable()->constrained('likes')->nullOnDelete();
            $table->decimal('compatibility_score', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('matched_at')->useCurrent();
            $table->timestamp('unmatched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_a_id', 'user_b_id'], 'uq_matches_canonical_pair');
            $table->index('matched_at');
        });

        Schema::create('match_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('questionnaire_score', 5, 2)->default(0);
            $table->decimal('interest_score', 5, 2)->default(0);
            $table->decimal('preference_score', 5, 2)->default(0);
            $table->decimal('activity_score', 5, 2)->default(0);
            $table->decimal('total_score', 5, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'candidate_id']);
            $table->index(['user_id', 'total_score']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('favorited_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'favorited_id']);
        });

        Schema::create('super_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();
            $table->index(['receiver_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
        });

        Schema::create('boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('views_gained')->default(0);
            $table->unsignedInteger('likes_gained')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('rewinds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id');
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'target_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewinds');
        Schema::dropIfExists('boosts');
        Schema::dropIfExists('super_likes');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('match_scores');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('likes');
    }
};
