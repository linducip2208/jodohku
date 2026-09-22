<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_and_me(): void
    {
        // Guest cannot access protected route.
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Member',
            'email' => 'member.auth@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'city' => 'Jakarta',
        ]);
        $register->assertCreated()->assertJsonStructure(['user' => ['id', 'email'], 'token']);
        $token = $register->json('token');
        $this->assertNotEmpty($token);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'member.auth@example.test',
            'password' => 'password123',
        ]);
        $login->assertOk()->assertJsonStructure(['token']);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJson(['email' => 'member.auth@example.test']);

        // Invalid credentials rejected.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'member.auth@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }
}
