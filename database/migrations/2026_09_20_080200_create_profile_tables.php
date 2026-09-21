<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('education', 100)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('ethnicity', 50)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->string('body_type', 30)->nullable();
            $table->string('smoking', 30)->nullable();
            $table->string('drinking', 30)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->unsignedTinyInteger('children_count')->default(0);
            $table->string('want_children', 30)->nullable();
            $table->string('relationship_goal', 40)->nullable();
            $table->string('languages', 255)->nullable();
            $table->string('zodiac', 20)->nullable();
            $table->boolean('is_complete')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->index('relationship_goal');
        });

        Schema::create('profile_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'sort_order']);
            $table->index(['user_id', 'is_primary']);
        });

        Schema::create('profile_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('profile_privacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('photos_visibility', 20)->default('public');
            $table->string('videos_visibility', 20)->default('public');
            $table->string('bio_visibility', 20)->default('public');
            $table->string('location_visibility', 20)->default('members_only');
            $table->string('online_visibility', 20)->default('members_only');
            $table->string('age_visibility', 20)->default('public');
            $table->boolean('show_distance')->default(true);
            $table->boolean('show_online_status')->default(true);
            $table->boolean('allow_profile_views')->default(true);
            $table->timestamps();
        });

        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();
            $table->index(['profile_user_id', 'viewed_at']);
            $table->index(['viewer_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_views');
        Schema::dropIfExists('profile_privacy');
        Schema::dropIfExists('profile_videos');
        Schema::dropIfExists('profile_photos');
        Schema::dropIfExists('profiles');
    }
};
