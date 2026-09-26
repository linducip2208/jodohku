<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('verification_token', 64)->nullable()->after('domain');
            $table->timestamp('domain_verified_at')->nullable()->after('verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['verification_token', 'domain_verified_at']);
        });
    }
};
