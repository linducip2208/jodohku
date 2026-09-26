<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('format', 20)->default('meetup')->after('status');
            $table->unsignedInteger('round_minutes')->default(5)->after('format');
        });

        Schema::create('speed_dating_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('round_no');
            $table->foreignId('user_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_a_id', 'user_b_id'], 'speed_rounds_pair_unique');
            $table->index(['event_id', 'round_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speed_dating_rounds');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['format', 'round_minutes']);
        });
    }
};
