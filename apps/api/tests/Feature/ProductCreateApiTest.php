<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCreateApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithProductCreatePermission(): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => 'Create Product',
            'code' => 'product.create',
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_user_with_permission_can_create_product(): void
    {
        $user = $this->userWithProductCreatePermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $pack = Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'ATK-100',
                'name' => 'Pulpen Gel',
                'units' => [
                    [
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
                'barcodes' => [
                    [
                        'barcode' => '8991000000001',
                        'is_primary' => true,
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.sku', 'ATK-100')
            ->assertJsonPath('data.name', 'Pulpen Gel')
            ->assertJsonCount(2, 'data.units')
            ->assertJsonCount(1, 'data.barcodes')
            ->assertJsonPath(
                'data.barcodes.0.barcode',
                '8991000000001'
            );

        $this->assertDatabaseHas('products', [
            'sku' => 'ATK-100',
            'name' => 'Pulpen Gel',
        ]);

        $product = Product::where('sku', 'ATK-100')->firstOrFail();

        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'is_base' => true,
        ]);
    }

    public function test_user_without_permission_cannot_create_product(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [])
            ->assertForbidden();
    }

    public function test_product_create_requires_required_fields(): void
    {
        $user = $this->userWithProductCreatePermission();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/products', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sku',
                'name',
                'units',
            ]);
    }

    public function test_product_must_have_exactly_one_base_unit(): void
    {
        $user = $this->userWithProductCreatePermission();

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $pack = Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'ATK-101',
                'name' => 'Pensil',
                'units' => [
                    [
                        'unit_id' => $pcs->id,
                        'conversion_to_base' => 1,
                        'selling_price' => 3000,
                        'is_base' => true,
                    ],
                    [
                        'unit_id' => $pack->id,
                        'conversion_to_base' => 12,
                        'selling_price' => 30000,
                        'is_base' => true,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('units');
    }

    public function test_base_unit_conversion_must_be_one(): void
    {
        $user = $this->userWithProductCreatePermission();

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'ATK-102',
                'name' => 'Spidol',
                'units' => [
                    [
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 2,
                        'selling_price' => 10000,
                        'is_base' => true,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('units');
    }

    public function test_duplicate_unit_in_same_product_is_rejected(): void
    {
        $user = $this->userWithProductCreatePermission();

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'ATK-103',
                'name' => 'Buku',
                'units' => [
                    [
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 1,
                        'selling_price' => 8000,
                        'is_base' => true,
                    ],
                    [
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 12,
                        'selling_price' => 80000,
                        'is_base' => false,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'units.0.unit_id'
            );
    }

    public function test_only_one_primary_barcode_is_allowed(): void
    {
        $user = $this->userWithProductCreatePermission();

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'ATK-104',
                'name' => 'Penghapus',
                'units' => [
                    [
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 1,
                        'selling_price' => 4000,
                        'is_base' => true,
                    ],
                ],
                'barcodes' => [
                    [
                        'barcode' => '8991000000002',
                        'is_primary' => true,
                    ],
                    [
                        'barcode' => '8991000000003',
                        'is_primary' => true,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('barcodes');
    }

    public function test_duplicate_sku_returns_conflict(): void
    {
        $user = $this->userWithProductCreatePermission();

        Product::create([
            'sku' => 'ATK-105',
            'name' => 'Existing Product',
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'ATK-105',
                'name' => 'Duplicate Product',
                'units' => [
                    [
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 1,
                        'selling_price' => 5000,
                        'is_base' => true,
                    ],
                ],
            ]);

        $response
            ->assertConflict()
            ->assertJson([
                'message' => 'Product conflicts with existing catalog data.',
            ]);
    }

    public function test_duplicate_barcode_returns_conflict(): void
    {
        $user = $this->userWithProductCreatePermission();

        $existingProduct = Product::create([
            'sku' => 'ATK-106',
            'name' => 'Existing Product',
        ]);

        ProductBarcode::create([
            'product_id' => $existingProduct->id,
            'barcode' => '8991000000004',
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/products', [
                'sku' => 'ATK-107',
                'name' => 'New Product',
                'units' => [
                    [
                        'unit_id' => $unit->id,
                        'conversion_to_base' => 1,
                        'selling_price' => 6000,
                        'is_base' => true,
                    ],
                ],
                'barcodes' => [
                    [
                        'barcode' => '8991000000004',
                        'is_primary' => true,
                    ],
                ],
            ]);

        $response
            ->assertConflict()
            ->assertJson([
                'message' => 'Product conflicts with existing catalog data.',
            ]);

        $this->assertDatabaseMissing('products', [
            'sku' => 'ATK-107',
        ]);
    }
}
