<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_roles(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $user->roles()->attach($role->id);

        $this->assertTrue(
            $user->fresh()->roles->contains('code', 'owner')
        );
    }

    public function test_role_can_have_permissions(): void
    {
        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => 'View Product',
            'code' => 'product.view',
        ]);

        $role->permissions()->attach($permission->id);

        $this->assertTrue(
            $role->fresh()->permissions->contains(
                'code',
                'product.view'
            )
        );
    }

    public function test_user_has_role_helper_works(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasRole('owner'));
        $this->assertFalse($user->hasRole('cashier'));
    }

    public function test_user_has_permission_through_role(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => 'View Product',
            'code' => 'product.view',
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $this->assertTrue(
            $user->hasPermission('product.view')
        );

        $this->assertFalse(
            $user->hasPermission('product.delete')
        );
    }

    public function test_me_endpoint_returns_roles_and_permissions(): void
    {
        $user = User::factory()->create([
            'username' => 'owner',
        ]);

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $viewPermission = Permission::create([
            'name' => 'View Product',
            'code' => 'product.view',
        ]);

        $createPermission = Permission::create([
            'name' => 'Create Product',
            'code' => 'product.create',
        ]);

        $role->permissions()->attach([
            $viewPermission->id,
            $createPermission->id,
        ]);

        $user->roles()->attach($role->id);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.user.username',
                'owner'
            )
            ->assertJsonCount(
                1,
                'data.user.roles'
            )
            ->assertJsonCount(
                2,
                'data.user.permissions'
            );

        $roles = $response->json('data.user.roles');
        $permissions = $response->json(
            'data.user.permissions'
        );

        $this->assertContains('owner', $roles);

        $this->assertContains(
            'product.view',
            $permissions
        );

        $this->assertContains(
            'product.create',
            $permissions
        );
    }

    public function test_duplicate_permissions_are_not_returned_twice(): void
    {
        $user = User::factory()->create();

        $owner = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $manager = Role::create([
            'name' => 'Manager',
            'code' => 'manager',
        ]);

        $permission = Permission::create([
            'name' => 'View Product',
            'code' => 'product.view',
        ]);

        $owner->permissions()->attach($permission->id);
        $manager->permissions()->attach($permission->id);

        $user->roles()->attach([
            $owner->id,
            $manager->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.user.roles'
            )
            ->assertJsonCount(
                1,
                'data.user.permissions'
            );

        $this->assertSame(
            ['product.view'],
            $response->json('data.user.permissions')
        );
    }

    public function test_user_with_permission_can_access_protected_route(): void
{
    Route::middleware([
        'auth:sanctum',
        'permission:product.view',
    ])->get('/test-permission', function () {
        return response()->json([
            'message' => 'Allowed.',
        ]);
    });

    $user = User::factory()->create();

    $role = Role::create([
        'name' => 'Owner',
        'code' => 'owner',
    ]);

    $permission = Permission::create([
        'name' => 'View Product',
        'code' => 'product.view',
    ]);

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);

    $response = $this
        ->actingAs($user)
        ->getJson('/test-permission');

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'Allowed.',
        ]);
}

public function test_user_without_permission_cannot_access_protected_route(): void
{
    Route::middleware([
        'auth:sanctum',
        'permission:product.view',
    ])->get('/test-permission-denied', function () {
        return response()->json([
            'message' => 'Allowed.',
        ]);
    });

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/test-permission-denied');

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Forbidden.',
        ]);
}

public function test_guest_cannot_access_permission_protected_route(): void
{
    Route::middleware([
        'auth:sanctum',
        'permission:product.view',
    ])->get('/test-permission-guest', function () {
        return response()->json([
            'message' => 'Allowed.',
        ]);
    });

    $response = $this->getJson('/test-permission-guest');

    $response->assertUnauthorized();
}
}