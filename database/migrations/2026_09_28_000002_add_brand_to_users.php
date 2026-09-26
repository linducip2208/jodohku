<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('account_type')
                ->constrained('brands')->nullOnDelete();
            $table->index(['brand_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'created_at']);
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
