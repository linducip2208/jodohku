<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDiscoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_discover_endpoint(): void
    {
        $me = User::factory()->create(['city' => 'Jakarta']);
        User::factory()->count(3)->create(['city' => 'Jakarta']);

        // Guest rejected.
        $this->getJson('/api/v1/discover')->assertUnauthorized();

        // Authenticated member gets discovery feed.
        $this->actingAs($me, 'sanctum')
            ->getJson('/api/v1/discover?per_page=5')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'display_name', 'city']], 'meta']);
    }
}
