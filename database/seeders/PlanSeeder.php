<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free', 'name' => 'Free', 'description' => 'Paket gratis untuk penjajakan awal.',
                'price' => 0, 'currency' => 'IDR', 'interval' => 'forever', 'duration_days' => 3650,
                'features' => ['daily_likes' => 20, 'super_likes_monthly' => 0, 'boosts_monthly' => 0],
                'daily_likes_limit' => 20, 'monthly_super_likes' => 0, 'monthly_boosts' => 0,
                'has_read_receipts' => false, 'has_incognito' => false, 'is_active' => true, 'sort_order' => 1,
            ],
            [
                'code' => 'premium_monthly', 'name' => 'Premium', 'description' => 'Like tanpa batas harian, super like, boost, read receipt.',
                'price' => 49000, 'currency' => 'IDR', 'interval' => 'monthly', 'duration_days' => 30,
                'features' => ['daily_likes' => 200, 'super_likes_monthly' => 10, 'boosts_monthly' => 2, 'read_receipts' => true],
                'daily_likes_limit' => 200, 'monthly_super_likes' => 10, 'monthly_boosts' => 2,
                'has_read_receipts' => true, 'has_incognito' => false, 'is_active' => true, 'sort_order' => 2,
            ],
            [
                'code' => 'vip_monthly', 'name' => 'VIP', 'description' => 'Semua Premium + incognito, prioritas matchmaker AI, pendampingan operator.',
                'price' => 149000, 'currency' => 'IDR', 'interval' => 'monthly', 'duration_days' => 30,
                'features' => ['daily_likes' => 500, 'super_likes_monthly' => 30, 'boosts_monthly' => 8, 'read_receipts' => true, 'incognito' => true, 'ai_matchmaker' => true],
                'daily_likes_limit' => 500, 'monthly_super_likes' => 30, 'monthly_boosts' => 8,
                'has_read_receipts' => true, 'has_incognito' => true, 'is_active' => true, 'sort_order' => 3,
            ],
        ];

        foreach ($plans as $p) {
            MembershipPlan::firstOrCreate(['code' => $p['code']], $p);
        }
    }
}
