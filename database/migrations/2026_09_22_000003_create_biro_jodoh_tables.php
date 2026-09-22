<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courtships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('stage', 20)->default('kenalan');
            $table->string('status', 20)->default('active');
            $table->string('guardian_name', 120)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('guardian_relation', 60)->nullable();
            $table->timestamp('guardian_approved_at')->nullable();
            $table->json('stage_history')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['initiator_id', 'status']);
            $table->index(['partner_id', 'status']);
        });

        Schema::create('counselors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('specialty', 120);
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_id')->constrained('counselors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('topic', 200);
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->string('status', 20)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['counselor_id', 'scheduled_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('compatibility_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'candidate_id']);
        });

        Schema::create('success_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('partner_name', 120);
            $table->text('story');
            $table->string('photo_path', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('success_stories');
        Schema::dropIfExists('compatibility_reports');
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('counselors');
        Schema::dropIfExists('courtships');
    }
};
