<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogIntegrityRulesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(
        string $permissionCode
    ): User {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => $permissionCode,
            'code' => $permissionCode,
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    public function test_product_create_rejects_inactive_category(): void
    {
        $user = $this->userWithPermission(
            'product.create'
        );

        $category = Category::create([
            'name' => 'Tidak Aktif',
            'is_active' => false,
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'INT-001',
                'name' => 'Produk',
                'category_id' => $category->id,
                'units' => [
                    [
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 1,
                        'selling_price' => 5000,
                        'is_base' => true,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'category_id'
            );
    }

    public function test_product_create_rejects_inactive_unit(): void
    {
        $user = $this->userWithPermission(
            'product.create'
        );

        $unit = Unit::create([
            'code' => 'OLD',
            'name' => 'Old Unit',
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'INT-002',
                'name' => 'Produk',
                'units' => [
                    [
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 1,
                        'selling_price' => 5000,
                        'is_base' => true,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'units.0.unit_id'
            );
    }

    public function test_active_product_cannot_use_inactive_category(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $category = Category::create([
            'name' => 'Inactive Category',
            'is_active' => false,
        ]);

        $product = Product::create([
            'sku' => 'INT-003',
            'name' => 'Produk',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'category_id' => $category->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'category_id'
            );
    }

    public function test_inactive_product_can_update_name_with_inactive_category(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $category = Category::create([
            'name' => 'Legacy',
            'is_active' => false,
        ]);

        $product = Product::create([
            'sku' => 'INT-004',
            'name' => 'Nama Lama',
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'name' => 'Nama Baru',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Nama Baru'
            );
    }

    public function test_product_cannot_be_reactivated_with_inactive_category(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $category = Category::create([
            'name' => 'Inactive',
            'is_active' => false,
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $product = Product::create([
            'sku' => 'INT-005',
            'name' => 'Produk',
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $product->productUnits()->create([
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 5000,
            'is_base' => true,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'category_id'
            );
    }

    public function test_product_cannot_be_reactivated_with_inactive_unit_master(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'INT-006',
            'name' => 'Produk',
            'is_active' => false,
        ]);

        $product->productUnits()->create([
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 5000,
            'is_base' => true,
            'is_active' => true,
        ]);

        $unit->update([
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'units'
            );
    }

    public function test_category_cannot_be_deactivated_when_used_by_active_product(): void
    {
        $user = $this->userWithPermission(
            'category.update'
        );

        $category = Category::create([
            'name' => 'ATK',
        ]);

        Product::create([
            'sku' => 'INT-007',
            'name' => 'Produk',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/categories/{$category->id}",
                [
                    'is_active' => false,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'is_active'
            );
    }

    public function test_unused_category_can_be_deactivated(): void
    {
        $user = $this->userWithPermission(
            'category.update'
        );

        $category = Category::create([
            'name' => 'Unused',
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/categories/{$category->id}",
                [
                    'is_active' => false,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                false
            );
    }

    public function test_unit_cannot_be_deactivated_when_used_by_active_product(): void
    {
        $user = $this->userWithPermission(
            'unit.update'
        );

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $product = Product::create([
            'sku' => 'INT-008',
            'name' => 'Produk',
            'is_active' => true,
        ]);

        $product->productUnits()->create([
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 5000,
            'is_base' => true,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/units/{$unit->id}",
                [
                    'is_active' => false,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'is_active'
            );
    }

    public function test_unused_unit_can_be_deactivated(): void
    {
        $user = $this->userWithPermission(
            'unit.update'
        );

        $unit = Unit::create([
            'code' => 'BOX',
            'name' => 'Box',
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
    }
}
