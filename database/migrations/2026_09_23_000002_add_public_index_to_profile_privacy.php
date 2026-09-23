<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-in public profile indexing (default OFF). Only when true — and
     * the account is active, non-incognito — may /u/{username} render and
     * appear in the sitemap. All other profiles stay noindex/private.
     */
    public function up(): void
    {
        Schema::table('profile_privacy', function (Blueprint $table) {
            $table->boolean('is_public_index')->default(false)->after('allow_profile_views');
        });
    }

    public function down(): void
    {
        Schema::table('profile_privacy', function (Blueprint $table) {
            $table->dropColumn('is_public_index');
        });
    }
};
