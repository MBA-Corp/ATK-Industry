<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentApiTest extends TestCase
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
            'name' => 'inventory.adjust',
            'code' => 'inventory.adjust',
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    private function product(
        string $sku = 'ADJ-001',
        string $unitCode = 'PCS',
        float $conversion = 1
    ): array {
        $product = Product::create([
            'sku' => $sku,
            'name' => "Product {$sku}",
        ]);

        $unit = Unit::create([
            'code' => $unitCode,
            'name' => $unitCode,
            'is_active' => true,
        ]);

        $productUnit = $product
            ->productUnits()
            ->create([
                'unit_id' => $unit->id,
                'conversion_to_base' => $conversion,
                'selling_price' => 10000,
                'is_base' => $conversion == 1,
                'is_active' => true,
            ]);

        return [
            $product,
            $productUnit,
        ];
    }

    public function test_stock_opname_sets_inventory_to_physical_quantity(): void
    {
        $user = $this->userWithPermission();

        [
            $product,
            $productUnit,
        ] = $this->product();

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 100,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'STOCK_OPNAME',

                    'product_unit_id' => $productUnit->id,

                    'quantity' => 92,

                    'reason' => 'Hasil stock opname',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.adjustment_type',
                'STOCK_OPNAME'
            )
            ->assertJsonPath(
                'data.quantity_before_base',
                '100.000000'
            )
            ->assertJsonPath(
                'data.quantity_delta_base',
                '-8.000000'
            )
            ->assertJsonPath(
                'data.quantity_after_base',
                '92.000000'
            );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 92,
            ]
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'product_id' => $product->id,
                'movement_type' => 'STOCK_OPNAME',
                'source_type' => 'STOCK_ADJUSTMENT',
                'quantity_delta_base' => -8,
                'quantity_after_base' => 92,
                'actor_user_id' => $user->id,
            ]
        );
    }

    public function test_damage_reduces_inventory_using_unit_conversion(): void
    {
        $user = $this->userWithPermission();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'ADJ-002',
            'PACK',
            12
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 120,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'DAMAGE',

                    'product_unit_id' => $productUnit->id,

                    'quantity' => 2,

                    'reason' => 'Kemasan rusak',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.quantity_delta_base',
                '-24.000000'
            )
            ->assertJsonPath(
                'data.quantity_after_base',
                '96.000000'
            );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 96,
            ]
        );
    }

    public function test_loss_reduces_inventory(): void
    {
        $user = $this->userWithPermission();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'ADJ-003'
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 20,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'LOSS',
                    'product_unit_id' => $productUnit->id,
                    'quantity' => 3,
                    'reason' => 'Barang hilang',
                ]
            )
            ->assertCreated();

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 17,
            ]
        );
    }

    public function test_positive_manual_adjustment_adds_inventory(): void
    {
        $user = $this->userWithPermission();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'ADJ-004'
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'ADJUSTMENT',

                    'product_unit_id' => $productUnit->id,

                    'quantity' => 5,

                    'reason' => 'Barang fisik ditemukan',
                ]
            )
            ->assertCreated();

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 5,
            ]
        );
    }

    public function test_negative_manual_adjustment_reduces_inventory(): void
    {
        $user = $this->userWithPermission();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'ADJ-005'
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 10,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'ADJUSTMENT',

                    'product_unit_id' => $productUnit->id,

                    'quantity' => -4,

                    'reason' => 'Koreksi stok',
                ]
            )
            ->assertCreated();

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 6,
            ]
        );
    }

    public function test_adjustment_cannot_make_inventory_negative(): void
    {
        $user = $this->userWithPermission();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'ADJ-006'
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 3,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'DAMAGE',

                    'product_unit_id' => $productUnit->id,

                    'quantity' => 5,

                    'reason' => 'Barang rusak',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'quantity'
            );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 3,
            ]
        );

        $this->assertDatabaseCount(
            'stock_adjustments',
            0
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0
        );
    }

    public function test_product_unit_must_belong_to_selected_product(): void
    {
        $user = $this->userWithPermission();

        [$productA] = $this->product(
            'ADJ-007-A',
            'PCS-A'
        );

        [
            ,
            $productUnitB,
        ] = $this->product(
            'ADJ-007-B',
            'PCS-B'
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$productA->id}/adjustments",
                [
                    'adjustment_type' => 'ADJUSTMENT',

                    'product_unit_id' => $productUnitB->id,

                    'quantity' => 1,

                    'reason' => 'Invalid unit',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'product_unit_id'
            );

        $this->assertDatabaseCount(
            'stock_adjustments',
            0
        );
    }

    public function test_zero_manual_adjustment_is_rejected(): void
    {
        $user = $this->userWithPermission();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'ADJ-008'
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'ADJUSTMENT',

                    'product_unit_id' => $productUnit->id,

                    'quantity' => 0,

                    'reason' => 'No change',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'quantity'
            );
    }

    public function test_user_without_permission_cannot_adjust_stock(): void
    {
        $user = User::factory()->create();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'ADJ-009'
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'ADJUSTMENT',

                    'product_unit_id' => $productUnit->id,

                    'quantity' => 5,

                    'reason' => 'Unauthorized adjustment',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'stock_adjustments',
            0
        );
    }

    public function test_guest_cannot_adjust_stock(): void
    {
        [
            $product,
            $productUnit,
        ] = $this->product(
            'ADJ-010'
        );

        $this
            ->postJson(
                "/api/v1/inventory/{$product->id}/adjustments",
                [
                    'adjustment_type' => 'ADJUSTMENT',

                    'product_unit_id' => $productUnit->id,

                    'quantity' => 5,

                    'reason' => 'Guest adjustment',
                ]
            )
            ->assertUnauthorized();

        $this->assertDatabaseCount(
            'stock_adjustments',
            0
        );
    }
}
