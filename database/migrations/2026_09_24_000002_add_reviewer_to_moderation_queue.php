<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * reviewer_id records WHO decided a queue item (single + bulk).
     * Additive: nullable, no backfill needed (old rows keep NULL).
     */
    public function up(): void
    {
        Schema::table('moderation_queue', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('moderation_queue', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_id');
        });
    }
};
