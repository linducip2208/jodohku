<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Private per-member notes about a match. Visible ONLY to the author
     * (never to the other party, counselors, or admins browsing profiles).
     * One row per (user, match), upserted.
     */
    public function up(): void
    {
        Schema::create('match_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->unique(['user_id', 'match_id']);
            $table->index(['match_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_notes');
    }
};
