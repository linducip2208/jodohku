<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InterestSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Kuliner', 'icon' => 'utensils'],
            ['name' => 'Traveling', 'icon' => 'plane'],
            ['name' => 'Fotografi', 'icon' => 'camera'],
            ['name' => 'Musik', 'icon' => 'music'],
            ['name' => 'Film', 'icon' => 'film'],
            ['name' => 'Olahraga Lari', 'icon' => 'runner'],
            ['name' => 'Sepak Bola', 'icon' => 'ball'],
            ['name' => 'Bulutangkis', 'icon' => 'shuttle'],
            ['name' => 'Membaca', 'icon' => 'book'],
            ['name' => 'Memasak', 'icon' => 'chef'],
            ['name' => 'Gaming', 'icon' => 'gamepad'],
            ['name' => 'Kopi', 'icon' => 'coffee'],
            ['name' => 'Hiking', 'icon' => 'mountain'],
            ['name' => 'Bersepeda', 'icon' => 'bike'],
            ['name' => 'Seni & Desain', 'icon' => 'palette'],
            ['name' => 'Teknologi', 'icon' => 'chip'],
            ['name' => 'Bisnis & UMKM', 'icon' => 'briefcase'],
            ['name' => 'Religi & Kajian', 'icon' => 'mosque'],
            ['name' => 'Volunteer', 'icon' => 'heart'],
            ['name' => 'Berkebun', 'icon' => 'leaf'],
        ];

        foreach ($items as $i) {
            Interest::firstOrCreate(
                ['slug' => Str::slug($i['name'])],
                ['name' => $i['name'], 'icon' => $i['icon'], 'is_active' => true]
            );
        }
    }
}
