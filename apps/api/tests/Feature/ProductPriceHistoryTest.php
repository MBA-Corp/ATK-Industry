<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => 'Update Product',
            'code' => 'product.update',
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    public function test_price_change_creates_history(): void
    {
        $user = $this->userWithPermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $product = Product::create([
            'sku' => 'ATK-300',
            'name' => 'Pulpen',
        ]);

        $productUnit = $product
            ->productUnits()
            ->create([
                'unit_id' => $pcs->id,
                'conversion_to_base' => 1,
                'selling_price' => 5000,
                'is_base' => true,
            ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'units' => [
                        [
                            'id' => $productUnit->id,
                            'unit_id' => $pcs->id,
                            'conversion_to_base' => 1,
                            'selling_price' => 6000,
                            'is_base' => true,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'product_price_histories',
            [
                'product_unit_id' => $productUnit->id,

                'old_price' => 5000,
                'new_price' => 6000,
                'changed_by' => $user->id,
            ]
        );
    }

    public function test_unchanged_price_does_not_create_history(): void
    {
        $user = $this->userWithPermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $product = Product::create([
            'sku' => 'ATK-301',
            'name' => 'Pensil',
        ]);

        $productUnit = $product
            ->productUnits()
            ->create([
                'unit_id' => $pcs->id,
                'conversion_to_base' => 1,
                'selling_price' => 3000,
                'is_base' => true,
            ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'units' => [
                        [
                            'id' => $productUnit->id,
                            'unit_id' => $pcs->id,
                            'conversion_to_base' => 1,
                            'selling_price' => 3000,
                            'is_base' => true,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'product_price_histories',
            0
        );
    }

    public function test_new_unit_creates_initial_price_history(): void
    {
        $user = $this->userWithPermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $pack = Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
        ]);

        $product = Product::create([
            'sku' => 'ATK-302',
            'name' => 'Spidol',
        ]);

        $baseUnit = $product
            ->productUnits()
            ->create([
                'unit_id' => $pcs->id,
                'conversion_to_base' => 1,
                'selling_price' => 5000,
                'is_base' => true,
            ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'units' => [
                        [
                            'id' => $baseUnit->id,
                            'unit_id' => $pcs->id,
                            'conversion_to_base' => 1,
                            'selling_price' => 5000,
                            'is_base' => true,
                        ],
                        [
                            'unit_id' => $pack->id,
                            'conversion_to_base' => 12,
                            'selling_price' => 55000,
                            'is_base' => false,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $newUnit = $product
            ->productUnits()
            ->where('unit_id', $pack->id)
            ->firstOrFail();

        $this->assertDatabaseHas(
            'product_price_histories',
            [
                'product_unit_id' => $newUnit->id,

                'old_price' => null,
                'new_price' => 55000,
                'changed_by' => $user->id,
            ]
        );
    }
}
