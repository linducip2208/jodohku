<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            // Commercial license: expired brands fall back to default Jodohku.
            $table->timestamp('expires_at')->nullable()->after('is_default');
            $table->unsignedInteger('max_users')->nullable()->after('expires_at');
            // Per-brand landing copy (hero title/subtitle/cta), fallback to defaults.
            $table->json('content')->nullable()->after('footer');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'max_users', 'content']);
        });
    }
};
