<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->boolean('share_report')->default(false)->after('notes');
            $table->foreignId('shared_report_id')->nullable()->after('share_report')
                ->constrained('compatibility_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_report_id');
            $table->dropColumn('share_report');
        });
    }
};
