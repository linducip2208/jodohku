<?php

namespace Database\Seeders;

use App\Models\CreditProduct;
use Illuminate\Database\Seeder;

class CreditProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'credits_50', 'name' => 'Kredit 50', 'credits' => 50, 'bonus_credits' => 0, 'price' => 15000, 'sort_order' => 1],
            ['code' => 'credits_120', 'name' => 'Kredit 120', 'credits' => 100, 'bonus_credits' => 20, 'price' => 30000, 'sort_order' => 2],
            ['code' => 'credits_300', 'name' => 'Kredit 300', 'credits' => 250, 'bonus_credits' => 50, 'price' => 70000, 'sort_order' => 3],
            ['code' => 'credits_700', 'name' => 'Kredit 700', 'credits' => 550, 'bonus_credits' => 150, 'price' => 150000, 'sort_order' => 4],
        ];

        foreach ($items as $i) {
            CreditProduct::firstOrCreate(['code' => $i['code']], $i + ['currency' => 'IDR', 'is_active' => true]);
        }
    }
}
