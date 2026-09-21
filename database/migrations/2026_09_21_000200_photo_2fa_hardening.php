<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_photos', function (Blueprint $table) {
            $table->string('status', 16)->default('pending')->after('is_private'); // pending|approved|rejected|hidden
            $table->string('file_hash', 64)->nullable()->after('status');
            $table->unsignedInteger('width')->nullable()->after('file_hash');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->foreignId('reviewed_by')->nullable()->after('height')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->index(['status', 'is_private']);
            $table->index('file_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('is_online');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });

        // Backfill: previously-approved photos keep their visibility.
        \Illuminate\Support\Facades\DB::table('profile_photos')
            ->where('is_approved', true)->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at']);
        });
        Schema::table('profile_photos', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_private']);
            $table->dropIndex(['file_hash']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'file_hash', 'width', 'height', 'reviewed_at']);
        });
    }
};
