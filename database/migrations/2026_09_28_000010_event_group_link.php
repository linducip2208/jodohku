<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('host_id')
                ->constrained('groups')->nullOnDelete();
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('owner_id')
                ->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
