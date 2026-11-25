<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_user_cannot_keep_using_an_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/me')->assertOk();
        $user->update(['is_active' => false]);
        $this->getJson('/api/v1/me')->assertForbidden();
        $this->getJson('/api/v1/products')->assertForbidden();
    }

    public function test_disabled_token_owner_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('regression')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }
}
