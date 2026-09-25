<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Premium platform batch: profile covers, Passport/travel mode,
     * privacy-preserving contact blocking, push device tokens,
     * referral + affiliate, group join requests. All additive.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // cover_path lives in 000002 (profile cover); referral_code new here.
            $table->string('referral_code', 16)->nullable()->unique()->after('cover_path');
            $table->string('passport_city', 120)->nullable();
            $table->string('passport_province', 120)->nullable();
            $table->string('passport_country', 120)->nullable();
            $table->decimal('passport_latitude', 10, 7)->nullable();
            $table->decimal('passport_longitude', 10, 7)->nullable();
            $table->boolean('passport_active')->default(false);
        });

        Schema::create('contact_hashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('phone_hash', 64);
            $table->timestamps();
            $table->unique(['user_id', 'phone_hash']);
            $table->index('phone_hash');
        });

        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('token', 500);
            $table->json('meta')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'platform', 'token'], 'push_tokens_user_platform_token_unique');
            $table->index(['user_id', 'disabled_at']);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('registered');
            $table->unsignedInteger('reward_credits')->default(0);
            $table->timestamps();
            $table->unique(['referrer_id', 'referred_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('affiliate_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 24)->unique();
            $table->string('status', 20)->default('pending');
            $table->decimal('commission_rate', 5, 4)->default(0.1000);
            $table->timestamps();
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained('affiliate_accounts')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['affiliate_account_id', 'status']);
        });

        Schema::create('group_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_join_requests');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_accounts');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('contact_hashes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'passport_city', 'passport_province', 'passport_country', 'passport_latitude', 'passport_longitude', 'passport_active']);
        });
    }
};
