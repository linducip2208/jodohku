<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            // Null = global plan (all brands). Set = this brand only.
            // Codes stay globally unique (use prefixed codes per brand).
            $table->foreignId('brand_id')->nullable()->after('id')
                ->constrained('brands')->nullOnDelete();
            $table->index(['brand_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'is_active']);
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
