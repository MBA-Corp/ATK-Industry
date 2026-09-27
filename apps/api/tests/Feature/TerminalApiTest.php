<?php

namespace Tests\Feature;

use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TerminalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_terminals(): void
    {
        $user = User::factory()->create();

        Terminal::create([
            'terminal_uuid' => Str::uuid()->toString(),
            'name' => 'Kasir Utama',
            'device_name' => 'POS-01',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/terminals');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Kasir Utama'
            )
            ->assertJsonPath(
                'data.0.device_name',
                'POS-01'
            )
            ->assertJsonPath(
                'data.0.is_active',
                true
            );
    }

    public function test_authenticated_user_can_register_terminal(): void
    {
        $user = User::factory()->create();

        $terminalUuid = Str::uuid()->toString();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/terminals', [
                'terminal_uuid' => $terminalUuid,
                'name' => 'Kasir Utama',
                'device_name' => 'POS-01',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.terminal_uuid',
                $terminalUuid
            )
            ->assertJsonPath(
                'data.name',
                'Kasir Utama'
            )
            ->assertJsonPath(
                'data.device_name',
                'POS-01'
            )
            ->assertJsonPath(
                'data.is_active',
                true
            )
            ->assertJsonPath(
                'data.last_seen_at',
                null
            );

        $this->assertDatabaseHas('terminals', [
            'terminal_uuid' => $terminalUuid,
            'name' => 'Kasir Utama',
            'device_name' => 'POS-01',
            'is_active' => true,
        ]);
    }

    public function test_terminal_uuid_must_be_valid_uuid(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/terminals', [
                'terminal_uuid' => 'bukan-uuid',
                'name' => 'Kasir Utama',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'terminal_uuid',
            ]);
    }

    public function test_terminal_uuid_must_be_unique(): void
    {
        $user = User::factory()->create();

        $terminalUuid = Str::uuid()->toString();

        Terminal::create([
            'terminal_uuid' => $terminalUuid,
            'name' => 'Kasir Lama',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/terminals', [
                'terminal_uuid' => $terminalUuid,
                'name' => 'Kasir Baru',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'terminal_uuid',
            ]);
    }

    public function test_guest_cannot_list_terminals(): void
    {
        $response = $this->getJson(
            '/api/v1/terminals'
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_register_terminal(): void
    {
        $response = $this->postJson(
            '/api/v1/terminals',
            [
                'terminal_uuid' => Str::uuid()->toString(),
                'name' => 'Kasir Utama',
            ]
        );

        $response->assertUnauthorized();
    }
}
