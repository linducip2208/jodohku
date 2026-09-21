<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InterestSeeder::class,
            QuestionSeeder::class,
            PlanSeeder::class,
            CreditProductSeeder::class,
            GiftSeeder::class,
            ProfanitySeeder::class,
            SettingsSeeder::class,
            PaymentGatewaySeeder::class,
            AiSeeder::class,
            AdminSeeder::class,
            MemberSeeder::class,
            VirtualMemberSeeder::class,
            TriggerSeeder::class,
            CouponBlogForumSeeder::class,
        ]);
    }
}
