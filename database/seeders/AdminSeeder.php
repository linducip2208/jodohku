<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Superadmin Jodohku',
                'email' => 'superadmin@jodohku.example.test',
                'username' => 'superadmin',
                'role' => UserRole::Superadmin,
                'account_type' => AccountType::Admin,
            ],
            [
                'name' => 'Admin Jodohku',
                'email' => 'admin@jodohku.example.test',
                'username' => 'admin',
                'role' => UserRole::Admin,
                'account_type' => AccountType::Admin,
            ],
            [
                'name' => 'Moderator Jodohku',
                'email' => 'moderator@jodohku.example.test',
                'username' => 'moderator',
                'role' => UserRole::Moderator,
                'account_type' => AccountType::Moderator,
            ],
            [
                'name' => 'Operator Jodohku',
                'email' => 'operator@jodohku.example.test',
                'username' => 'operator',
                'role' => UserRole::Operator,
                'account_type' => AccountType::Operator,
            ],
        ];

        foreach ($accounts as $a) {
            User::firstOrCreate(
                ['email' => $a['email']],
                [
                    'name' => $a['name'],
                    'username' => $a['username'],
                    'display_name' => $a['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'account_type' => $a['account_type'],
                    'role' => $a['role'],
                    'status' => UserStatus::Active,
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'is_verified' => true,
                ]
            );
        }
    }
}
