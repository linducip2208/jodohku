<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'users_lat_lng_index');
        });
        // NOTE: credit_transactions already has (reference_type, reference_id)
        // index from 2026_09_20_080900_create_monetization_tables — no duplicate.
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_lat_lng_index');
        });
    }
};
