<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 100)->unique();
            $table->string('icon', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interest_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'interest_id']);
        });

        Schema::create('partner_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->string('gender_preference', 30)->nullable();
            $table->unsignedSmallInteger('max_distance_km')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('religion_importance', 20)->default('neutral');
            $table->string('education', 100)->nullable();
            $table->string('education_importance', 20)->default('neutral');
            $table->string('marital_status', 30)->nullable();
            $table->string('marital_importance', 20)->default('neutral');
            $table->string('smoking_preference', 30)->nullable();
            $table->string('drinking_preference', 30)->nullable();
            $table->string('relationship_goal', 40)->nullable();
            $table->string('relationship_importance', 20)->default('neutral');
            $table->unsignedSmallInteger('min_height_cm')->nullable();
            $table->unsignedSmallInteger('max_height_cm')->nullable();
            $table->boolean('verified_only')->default(false);
            $table->boolean('photo_only')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_preferences');
        Schema::dropIfExists('user_interests');
        Schema::dropIfExists('interests');
    }
};
