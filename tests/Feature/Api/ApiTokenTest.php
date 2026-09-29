<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_create_and_use_a_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/tokens', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-suite',
            'abilities' => ['tracking:read'],
        ]);

        $token = $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('abilities.0', 'tracking:read')
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_invalid_credentials_do_not_create_a_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/tokens', [
            'email' => $user->email,
            'password' => 'incorrect-password',
            'device_name' => 'test-suite',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unverified_user_cannot_create_a_token(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/auth/tokens', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-suite',
        ])->assertUnprocessable();
    }

    public function test_user_can_revoke_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-suite')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/auth/token')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
