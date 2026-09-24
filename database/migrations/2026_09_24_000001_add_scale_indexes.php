<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scale indexes, each justified by a measured/hot query path
     * (see docs/SCALE-AUDIT.md section D). Purely additive: no column
     * changes, no drops, safe to run on a live database (online DDL on
     * InnoDB; brief metadata lock only).
     *
     * - notifications morph+time: every inbox load + unread count scans
     *   notifiable_type/notifiable_id with no index today.
     * - matches per-side active: WHERE (user_a=? OR user_b=?) AND is_active
     *   cannot use the canonical unique index efficiently.
     * - users geo/flags: discovery bounding boxes + verified/online/premium
     *   filters + PSEO counters + admin filters.
     * - audit_logs time: retention deletes (actor/action already indexed).
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'idx_notifications_notifiable_created');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_notifiable_read');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->index(['user_a_id', 'is_active'], 'idx_matches_a_active');
            $table->index(['user_b_id', 'is_active'], 'idx_matches_b_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'idx_users_geo');
            $table->index('is_online', 'idx_users_online');
            $table->index('is_verified', 'idx_users_verified');
            $table->index('is_premium', 'idx_users_premium');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at', 'idx_audit_logs_created');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_created');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_geo');
            $table->dropIndex('idx_users_online');
            $table->dropIndex('idx_users_verified');
            $table->dropIndex('idx_users_premium');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('idx_matches_a_active');
            $table->dropIndex('idx_matches_b_active');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_notifiable_created');
            $table->dropIndex('idx_notifications_notifiable_read');
        });
    }
};
