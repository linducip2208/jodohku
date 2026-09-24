<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Social Dating platform tables (additive only).
     *
     * Reuses existing primitives: Block (safety), Post/Comment/PostLike,
     * Group/GroupMember (communities), MessageReaction (chat), Report +
     * ModerationQueue (morph safety), NotificationPreference (gates).
     */
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_id', 'followed_id']);
            $table->index(['followed_id', 'created_at']);
            $table->index(['follower_id', 'created_at']);
        });

        Schema::create('mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('muter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('muted_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['muter_id', 'muted_id']);
        });

        Schema::create('post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20)->default('like');
            $table->timestamps();
            $table->unique(['post_id', 'user_id', 'type']);
            $table->index(['post_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20)->default('like');
            $table->timestamps();
            $table->unique(['comment_id', 'user_id', 'type']);
        });

        Schema::create('post_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('post_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->timestamps();
            $table->index(['post_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 120);
            $table->unsignedInteger('posts_count')->default(0);
            $table->timestamps();
            $table->index('posts_count');
        });

        Schema::create('post_hashtag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('hashtag_id')->constrained('hashtags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['post_id', 'hashtag_id']);
            $table->index(['hashtag_id', 'created_at']);
        });

        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->string('mentionable_type', 100);
            $table->unsignedBigInteger('mentionable_id');
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentioned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['mentioned_user_id', 'created_at']);
            $table->index(['mentionable_type', 'mentionable_id']);
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20)->default('text');
            $table->string('media_path', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('visibility', 20)->default('public');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
            $table->index(['visibility', 'expires_at']);
        });

        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['story_id', 'user_id']);
        });

        Schema::create('story_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20)->default('like');
            $table->timestamps();
            $table->unique(['story_id', 'user_id', 'type']);
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('profile_privacy', function (Blueprint $table) {
            $table->string('followers_visibility', 20)->default('public')->after('bio_visibility');
            $table->string('following_visibility', 20)->default('public')->after('followers_visibility');
            $table->string('posts_visibility', 20)->default('public')->after('following_visibility');
            $table->string('stories_visibility', 20)->default('public')->after('posts_visibility');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('shares_count')->default(0)->after('comments_count');
            $table->timestamp('boosted_until')->nullable()->after('shares_count');
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('push_follows')->default(true)->after('push_super_likes');
            $table->boolean('push_comments')->default(true)->after('push_follows');
            $table->boolean('push_mentions')->default(true)->after('push_comments');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->string('category', 80)->nullable()->after('description');
            $table->json('interests')->nullable()->after('category');
            $table->text('rules')->nullable()->after('interests');
            $table->unsignedInteger('posts_count')->default(0)->after('members_count');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['category', 'interests', 'rules', 'posts_count']);
        });
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['push_follows', 'push_comments', 'push_mentions']);
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['shares_count', 'boosted_until']);
        });
        Schema::table('profile_privacy', function (Blueprint $table) {
            $table->dropColumn(['followers_visibility', 'following_visibility', 'posts_visibility', 'stories_visibility']);
        });
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('story_reactions');
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('mentions');
        Schema::dropIfExists('post_hashtag');
        Schema::dropIfExists('hashtags');
        Schema::dropIfExists('post_shares');
        Schema::dropIfExists('post_bookmarks');
        Schema::dropIfExists('comment_reactions');
        Schema::dropIfExists('post_reactions');
        Schema::dropIfExists('mutes');
        Schema::dropIfExists('follows');
    }
};
