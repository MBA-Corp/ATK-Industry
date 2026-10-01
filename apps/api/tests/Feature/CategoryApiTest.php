<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
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

    public function test_authenticated_user_can_list_categories(): void
    {
        $user = User::factory()->create();

        Category::create([
            'name' => 'Alat Tulis',
        ]);

        Category::create([
            'name' => 'Kertas',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/categories');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_categories_can_be_searched_and_filtered(): void
    {
        $user = User::factory()->create();

        Category::create([
            'name' => 'Alat Tulis',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Elektronik',
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/categories?search=Alat&is_active=1'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Alat Tulis'
            );
    }

    public function test_user_with_permission_can_create_category(): void
    {
        $user = $this->userWithPermission(
            'category.create'
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/categories', [
                'name' => 'Buku',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Buku'
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );

        $this->assertDatabaseHas('categories', [
            'name' => 'Buku',
            'is_active' => true,
        ]);
    }

    public function test_user_without_permission_cannot_create_category(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/categories', [
                'name' => 'Buku',
            ])
            ->assertForbidden();
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        $user = $this->userWithPermission(
            'category.create'
        );

        Category::create([
            'name' => 'Buku',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/categories', [
                'name' => 'Buku',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_user_with_permission_can_update_category(): void
    {
        $user = $this->userWithPermission(
            'category.update'
        );

        $category = Category::create([
            'name' => 'Buku Lama',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/categories/{$category->id}",
                [
                    'name' => 'Buku Baru',
                    'is_active' => false,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Buku Baru'
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Buku Baru',
            'is_active' => false,
        ]);
    }

    public function test_category_update_cannot_use_existing_name(): void
    {
        $user = $this->userWithPermission(
            'category.update'
        );

        Category::create([
            'name' => 'Buku',
        ]);

        $category = Category::create([
            'name' => 'Kertas',
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/categories/{$category->id}",
                [
                    'name' => 'Buku',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_guest_cannot_access_categories(): void
    {
        $this
            ->getJson('/api/v1/categories')
            ->assertUnauthorized();
    }
}
