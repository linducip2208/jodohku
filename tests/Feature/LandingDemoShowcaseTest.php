<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingDemoShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_shows_demo_members_without_authentication(): void
    {
        $demo = User::factory()->create([
            'name' => 'Ayu Demo',
            'display_name' => 'Ayu',
            'is_demo' => true,
            'status' => 'active',
            'is_verified' => true,
        ]);
        $demo->profile()->create([
            'headline' => 'Serius mencari pasangan hidup',
            'bio' => 'Profil demo.',
            'occupation' => 'Designer',
            'education' => 'S1',
            'relationship_goal' => 'marriage',
            'is_complete' => true,
        ]);

        $real = User::factory()->create([
            'name' => 'Real Member',
            'display_name' => 'Real Member',
            'is_demo' => false,
            'status' => 'active',
        ]);
        $real->profile()->create(['is_complete' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Lihat contoh member Jodohku')
            ->assertSee('Ayu')
            ->assertDontSee('Real Member');
    }
}
