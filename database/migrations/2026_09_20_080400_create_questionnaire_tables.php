<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique()->default('');
            $table->string('slug', 50)->unique()->default('');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('questionnaire_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('title', 150)->default('');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_category_id')->nullable()->constrained('question_categories')->nullOnDelete();
            $table->foreignId('questionnaire_version_id')->nullable()->constrained('questionnaire_versions')->nullOnDelete();
            $table->string('category_key', 50)->default('personality');
            $table->string('type', 30)->default('single_choice');
            $table->string('question_text', 500);
            $table->text('help_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category_key', 'is_active']);
            $table->index(['questionnaire_version_id', 'sort_order']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('option_text', 300);
            $table->string('option_value', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();
            $table->index(['question_id', 'sort_order']);
        });

        Schema::create('questionnaire_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('questionnaire_version_id')->nullable()->constrained('questionnaire_versions')->nullOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained('question_options')->nullOnDelete();
            $table->text('answer_text')->nullable();
            $table->string('answer_value', 255)->nullable();
            $table->unsignedInteger('answer_score')->default(0);
            $table->string('importance', 20)->default('neutral');
            $table->timestamps();
            $table->unique(['user_id', 'question_id', 'questionnaire_version_id'], 'uq_answers_user_question_version');
            $table->index(['user_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_answers');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('questionnaire_versions');
        Schema::dropIfExists('question_categories');
    }
};
