<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demo-data marker: lets the demo seeder/reset and the admin user
     * list distinguish generated demo accounts from real users without
     * touching any production domain logic.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_premium');
            $table->index(['is_demo', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_demo', 'status']);
            $table->dropColumn('is_demo');
        });
    }
};
