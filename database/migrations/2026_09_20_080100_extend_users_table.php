<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('account_type', 20)->default('real')->after('password');
            $table->string('role', 20)->default('member')->after('account_type');
            $table->string('status', 30)->default('active')->after('role');
            $table->string('username', 50)->nullable()->unique()->after('status');
            $table->string('display_name', 100)->nullable()->after('username');
            $table->date('date_of_birth')->nullable()->after('display_name');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->boolean('is_verified')->default(false)->after('gender');
            $table->boolean('is_premium')->default(false)->after('is_verified');
            $table->boolean('is_online')->default(false)->after('is_premium');
            $table->timestamp('last_active_at')->nullable()->after('is_online');
            $table->decimal('latitude', 10, 7)->nullable()->after('last_active_at');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('city', 100)->nullable()->after('longitude');
            $table->string('province', 100)->nullable()->after('city');
            $table->string('country', 100)->nullable()->after('province');
            $table->string('avatar_path')->nullable()->after('country');
            $table->unsignedTinyInteger('profile_completion')->default(0)->after('avatar_path');
            $table->softDeletes()->after('updated_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('account_type');
            $table->index('role');
            $table->index('status');
            $table->index('gender');
            $table->index(['city', 'province']);
            $table->index('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type']);
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropIndex(['gender']);
            $table->dropIndex(['city', 'province']);
            $table->dropIndex(['last_active_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropUnique(['phone']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'phone',
                'phone_verified_at',
                'account_type',
                'role',
                'status',
                'username',
                'display_name',
                'date_of_birth',
                'gender',
                'is_verified',
                'is_premium',
                'is_online',
                'last_active_at',
                'latitude',
                'longitude',
                'city',
                'province',
                'country',
                'avatar_path',
                'profile_completion',
            ]);
        });
    }
};
