<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dating UX: named saved discovery filters + profile Q&A prompts.
     * Additive only.
     */
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->json('filters');
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->json('prompts')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('prompts');
        });
        Schema::dropIfExists('saved_filters');
    }
};
