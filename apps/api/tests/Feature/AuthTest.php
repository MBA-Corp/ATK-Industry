<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_with_username(): void
    {
        $user = User::factory()->create([
            'username' => 'owner',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'username' => 'owner',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->create([
            'username' => 'owner',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'username' => 'owner',
            'password' => 'salah-password',
        ]);

        $response->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'inactive-user',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $response = $this->postJson('/login', [
            'username' => 'inactive-user',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->create([
            'username' => 'owner',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.username', 'owner');
    }

    public function test_guest_cannot_access_me_endpoint(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'username' => 'owner',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/logout');

        $response->assertOk();

        $this->assertGuest();
    }
}
