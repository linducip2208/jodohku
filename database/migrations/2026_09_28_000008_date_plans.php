<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('date_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('place', 200)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('proposed');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_plans');
    }
};
