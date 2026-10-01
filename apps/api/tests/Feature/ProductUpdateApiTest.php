<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithProductUpdatePermission(): User
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

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_user_without_permission_cannot_update_product(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'sku' => 'ATK-200',
            'name' => 'Pulpen',
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'name' => 'Pulpen Baru',
                ]
            )
            ->assertForbidden();
    }

    public function test_product_can_be_partially_updated(): void
    {
        $user = $this->userWithProductUpdatePermission();

        $product = Product::create([
            'sku' => 'ATK-201',
            'name' => 'Pulpen Lama',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'name' => 'Pulpen Baru',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Pulpen Baru'
            );

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sku' => 'ATK-201',
            'name' => 'Pulpen Baru',
        ]);
    }

    public function test_existing_product_unit_keeps_same_id_when_updated(): void
    {
        $user = $this->userWithProductUpdatePermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $product = Product::create([
            'sku' => 'ATK-202',
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

        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'units' => [
                        [
                            'id' => $productUnit->id,
                            'unit_id' => $pcs->id,
                            'conversion_to_base' => 1,
                            'selling_price' => 4000,
                            'is_base' => true,
                        ],
                    ],
                ]
            );

        $response->assertOk();

        $this->assertDatabaseHas('product_units', [
            'id' => $productUnit->id,
            'product_id' => $product->id,
            'selling_price' => 4000,
        ]);

        $this->assertSame(
            1,
            $product->productUnits()->count()
        );
    }

    public function test_new_product_unit_can_be_added(): void
    {
        $user = $this->userWithProductUpdatePermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $pack = Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
        ]);

        $product = Product::create([
            'sku' => 'ATK-203',
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

        $response = $this
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
            );

        $response->assertOk();

        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $pack->id,
            'selling_price' => 55000,
        ]);
    }

    public function test_removed_product_unit_is_deactivated_not_deleted(): void
    {
        $user = $this->userWithProductUpdatePermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $pack = Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
        ]);

        $product = Product::create([
            'sku' => 'ATK-204',
            'name' => 'Penghapus',
        ]);

        $baseUnit = $product
            ->productUnits()
            ->create([
                'unit_id' => $pcs->id,
                'conversion_to_base' => 1,
                'selling_price' => 3000,
                'is_base' => true,
            ]);

        $packUnit = $product
            ->productUnits()
            ->create([
                'unit_id' => $pack->id,
                'conversion_to_base' => 12,
                'selling_price' => 30000,
                'is_base' => false,
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
                            'selling_price' => 3000,
                            'is_base' => true,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas('product_units', [
            'id' => $packUnit->id,
            'product_id' => $product->id,
            'is_active' => false,
        ]);
    }

    public function test_product_unit_from_another_product_is_rejected(): void
    {
        $user = $this->userWithProductUpdatePermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $productA = Product::create([
            'sku' => 'ATK-205',
            'name' => 'Produk A',
        ]);

        $productB = Product::create([
            'sku' => 'ATK-206',
            'name' => 'Produk B',
        ]);

        $foreignUnit = $productB
            ->productUnits()
            ->create([
                'unit_id' => $pcs->id,
                'conversion_to_base' => 1,
                'selling_price' => 5000,
                'is_base' => true,
            ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$productA->id}",
                [
                    'units' => [
                        [
                            'id' => $foreignUnit->id,
                            'unit_id' => $pcs->id,
                            'conversion_to_base' => 1,
                            'selling_price' => 6000,
                            'is_base' => true,
                        ],
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'units.0.id'
            );
    }

    public function test_duplicate_sku_returns_conflict(): void
    {
        $user = $this->userWithProductUpdatePermission();

        $product = Product::create([
            'sku' => 'ATK-207',
            'name' => 'Produk Lama',
        ]);

        Product::create([
            'sku' => 'ATK-208',
            'name' => 'Produk Lain',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'sku' => 'ATK-208',
                ]
            );

        $response->assertConflict();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sku' => 'ATK-207',
        ]);
    }

    public function test_transaction_rolls_back_when_barcode_conflicts(): void
    {
        $user = $this->userWithProductUpdatePermission();

        $existingProduct = Product::create([
            'sku' => 'ATK-209',
            'name' => 'Produk Existing',
        ]);

        $existingProduct
            ->barcodes()
            ->create([
                'barcode' => '8991000000099',
                'is_primary' => true,
            ]);

        $product = Product::create([
            'sku' => 'ATK-210',
            'name' => 'Nama Lama',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}",
                [
                    'name' => 'Nama Baru',
                    'barcodes' => [
                        [
                            'barcode' => '8991000000099',
                            'is_primary' => true,
                        ],
                    ],
                ]
            );

        $response->assertConflict();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nama Lama',
        ]);

        $this->assertDatabaseMissing('product_barcodes', [
            'product_id' => $product->id,
            'barcode' => '8991000000099',
        ]);
    }
}
