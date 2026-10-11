<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_and_list_videos(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        $this->withToken($token)
            ->getJson('/api/videos')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/videos')->assertUnauthorized();
    }
}
