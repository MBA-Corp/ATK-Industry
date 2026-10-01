<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $code): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => $code,
            'code' => $code,
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_authenticated_user_can_list_units(): void
    {
        $user = User::factory()->create();

        Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/units');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_units_can_be_searched_and_filtered(): void
    {
        $user = User::factory()->create();

        Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'is_active' => true,
        ]);

        Unit::create([
            'code' => 'BOX',
            'name' => 'Box',
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/units?search=PCS&is_active=1'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.code',
                'PCS'
            );
    }

    public function test_user_with_permission_can_create_unit(): void
    {
        $user = $this->userWithPermission(
            'unit.create'
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/units', [
                'code' => 'pcs',
                'name' => 'Pieces',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'PCS'
            )
            ->assertJsonPath(
                'data.name',
                'Pieces'
            );

        $this->assertDatabaseHas('units', [
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);
    }

    public function test_user_without_permission_cannot_create_unit(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/units', [
                'code' => 'PCS',
                'name' => 'Pieces',
            ])
            ->assertForbidden();
    }

    public function test_duplicate_unit_code_is_rejected(): void
    {
        $user = $this->userWithPermission(
            'unit.create'
        );

        Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/units', [
                'code' => 'pcs',
                'name' => 'Pieces Baru',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_user_with_permission_can_update_unit(): void
    {
        $user = $this->userWithPermission(
            'unit.update'
        );

        $unit = Unit::create([
            'code' => 'PCK',
            'name' => 'Pack Lama',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/units/{$unit->id}",
                [
                    'code' => 'pack',
                    'name' => 'Pack',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.code',
                'PACK'
            )
            ->assertJsonPath(
                'data.name',
                'Pack'
            );

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'code' => 'PACK',
            'name' => 'Pack',
        ]);
    }

    public function test_unit_update_cannot_use_existing_code(): void
    {
        $user = $this->userWithPermission(
            'unit.update'
        );

        Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $unit = Unit::create([
            'code' => 'BOX',
            'name' => 'Box',
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/units/{$unit->id}",
                [
                    'code' => 'pcs',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_unit_can_be_deactivated(): void
    {
        $user = $this->userWithPermission(
            'unit.update'
        );

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/units/{$unit->id}",
                [
                    'is_active' => false,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'is_active' => false,
        ]);
    }

    public function test_guest_cannot_access_units(): void
    {
        $this
            ->getJson('/api/v1/units')
            ->assertUnauthorized();
    }
}
