<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('tagline', 200)->nullable();
            $table->string('primary_color', 7)->default('#f43f5e');
            $table->string('secondary_color', 7)->default('#8b5cf6');
            $table->string('logo_path', 500)->nullable();
            $table->string('favicon_path', 500)->nullable();
            $table->string('domain', 190)->nullable()->unique();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->json('features')->nullable();
            $table->json('footer')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
