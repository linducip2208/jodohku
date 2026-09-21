<?php

namespace Database\Factories;

use App\Models\Interest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Interest>
 */
class InterestFactory extends Factory
{
    protected $model = Interest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Traveling', 'Kuliner', 'Fotografi', 'Musik', 'Film', 'Olahraga',
            'Membaca', 'Memasak', 'Gaming', 'Hiking', 'Yoga', 'Berkebun',
            'Melukis', 'Menari', 'Kopi', 'Fashion', 'Teknologi', 'Bisnis',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'icon' => null,
            'is_active' => true,
        ];
    }
}
