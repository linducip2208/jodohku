<?php

namespace Database\Factories;

use App\Enums\MaritalStatus;
use App\Enums\RelationshipGoal;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'headline' => fake()->sentence(6),
            'bio' => fake()->paragraph(3),
            'occupation' => fake()->jobTitle(),
            'education' => fake()->randomElement(['SMA', 'D3', 'S1', 'S2', 'S3']),
            'religion' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']),
            'ethnicity' => fake()->randomElement(['Jawa', 'Sunda', 'Batak', 'Minang', 'Betawi', 'Bugis']),
            'height_cm' => fake()->numberBetween(150, 190),
            'weight_kg' => fake()->numberBetween(45, 95),
            'body_type' => fake()->randomElement(['slim', 'athletic', 'average', 'curvy']),
            'smoking' => fake()->randomElement(['no', 'occasionally', 'yes']),
            'drinking' => fake()->randomElement(['no', 'occasionally', 'yes']),
            'marital_status' => fake()->randomElement(MaritalStatus::cases()),
            'children_count' => fake()->numberBetween(0, 3),
            'want_children' => fake()->randomElement(['yes', 'no', 'maybe']),
            'relationship_goal' => fake()->randomElement(RelationshipGoal::cases()),
            'languages' => 'Indonesian, English',
            'zodiac' => fake()->randomElement(['Aries', 'Taurus', 'Gemini', 'Leo', 'Virgo', 'Libra']),
            'is_complete' => true,
            'is_featured' => false,
        ];
    }
}
