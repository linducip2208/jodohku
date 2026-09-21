<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);

        return [
            'name' => fake()->name($gender === Gender::Male ? 'male' : 'female'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->unique()->numerify('+628##########'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'account_type' => AccountType::Real,
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'username' => fake()->unique()->userName(),
            'display_name' => fake()->firstName(),
            'date_of_birth' => fake()->dateTimeBetween('-45 years', '-18 years')->format('Y-m-d'),
            'gender' => $gender,
            'is_verified' => false,
            'is_premium' => false,
            'is_online' => false,
            'last_active_at' => now(),
            'latitude' => fake()->latitude(-6.5, -6.0),
            'longitude' => fake()->longitude(106.5, 107.0),
            'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang']),
            'province' => fake()->randomElement(['DKI Jakarta', 'Jawa Barat', 'Jawa Timur', 'Sumatera Utara', 'Jawa Tengah']),
            'country' => 'Indonesia',
            'avatar_path' => null,
            'profile_completion' => fake()->numberBetween(20, 100),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Premium,
            'is_premium' => true,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'status' => UserStatus::Active,
        ]);
    }

    public function virtual(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Virtual,
        ]);
    }

    public function ai(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Ai,
        ]);
    }

    public function staff(string $role = 'moderator'): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Moderator,
            'role' => UserRole::from($role),
        ]);
    }
}
