<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_conversations', 'ai_paused_at')) {
                $table->timestamp('ai_paused_at')->nullable()->after('handed_over_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('virtual_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('virtual_conversations', 'ai_paused_at')) {
                $table->dropColumn('ai_paused_at');
            }
        });
    }
};
