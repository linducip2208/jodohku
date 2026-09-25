<?php

use App\Models\ContactHash;
use App\Services\ContactBlockService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_hash', 64)->nullable()->after('phone');
            $table->index('phone_hash');
        });
        // Backfill in chunks (portable across MySQL/SQLite, any table size).
        DB::table('users')->select(['id', 'phone'])->orderBy('id')->chunkById(1000, function ($users) {
            foreach ($users as $u) {
                $normalized = ContactBlockService::normalizePhone((string) ($u->phone ?? ''));
                if ($normalized !== null) {
                    DB::table('users')->where('id', $u->id)->update(['phone_hash' => ContactHash::hash($normalized)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_hash']);
            $table->dropColumn('phone_hash');
        });
    }
};
