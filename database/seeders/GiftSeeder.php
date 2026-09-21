<?php

namespace Database\Seeders;

use App\Models\Gift;
use Illuminate\Database\Seeder;

class GiftSeeder extends Seeder
{
    public function run(): void
    {
        $gifts = [
            ['code' => 'rose', 'name' => 'Mawar', 'description' => 'Tanda perhatian klasik.', 'icon_path' => 'gifts/rose.png', 'credit_price' => 10, 'sort_order' => 1],
            ['code' => 'coffee', 'name' => 'Kopi Hangat', 'description' => 'Ajakan ngopi santai.', 'icon_path' => 'gifts/coffee.png', 'credit_price' => 15, 'sort_order' => 2],
            ['code' => 'bouquet', 'name' => 'Buket Bunga', 'description' => 'Keseriusan tahap awal.', 'icon_path' => 'gifts/bouquet.png', 'credit_price' => 50, 'sort_order' => 3],
            ['code' => 'ring', 'name' => 'Cincin Komitmen', 'description' => 'Simbol keseriusan menikah.', 'icon_path' => 'gifts/ring.png', 'credit_price' => 500, 'sort_order' => 4],
            ['code' => 'star', 'name' => 'Bintang Bersinar', 'description' => 'Apresiasi profil menarik.', 'icon_path' => 'gifts/star.png', 'credit_price' => 25, 'sort_order' => 5],
        ];

        foreach ($gifts as $g) {
            Gift::firstOrCreate(['code' => $g['code']], $g + ['is_active' => true]);
        }
    }
}
