<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceivingInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => 'purchase.receive',
            'code' => 'purchase.receive',
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    private function purchaseFixture(
        string $sku,
        float $conversion,
        float $ordered = 10
    ): array {
        $supplier = Supplier::create([
            'code' => "SUP-{$sku}",
            'name' => "Supplier {$sku}",
        ]);

        $product = Product::create([
            'sku' => $sku,
            'name' => "Product {$sku}",
        ]);

        $unit = Unit::create([
            'code' => "U-{$sku}",
            'name' => "Unit {$sku}",
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

        $productSupplier = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,

                'last_purchase_price' => 5000,

                'is_active' => true,
            ]);

        $purchase = Purchase::create([
            'purchase_number' => "PUR-{$sku}",

            'supplier_id' => $supplier->id,

            'purchase_date' => '2026-10-01',

            'status' => 'DRAFT',
        ]);

        $purchaseItem = $purchase
            ->items()
            ->create([
                'product_supplier_id' => $productSupplier->id,

                'product_unit_id' => $productUnit->id,

                'conversion_to_base' => $conversion,

                'qty_ordered' => $ordered,

                'qty_received' => 0,

                'purchase_price' => 5000,

                'line_total' => (int) round(
                    $ordered * 5000
                ),
            ]);

        return [
            $product,
            $productUnit,
            $purchase,
            $purchaseItem,
        ];
    }

    public function test_receiving_adds_actual_quantity_to_inventory(): void
    {
        $user = $this->user();

        [
            $product,
            ,
            $purchase,
            $purchaseItem,
        ] = $this->purchaseFixture(
            'INV-RCV-001',
            1
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => 8,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,

                'quantity_base' => 8,
            ]
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'product_id' => $product->id,

                'movement_type' => 'PURCHASE_RECEIPT',

                'source_type' => 'PURCHASE_RECEIPT_ITEM',

                'source_quantity' => 8,

                'conversion_to_base' => 1,

                'quantity_delta_base' => 8,

                'actor_user_id' => $user->id,
            ]
        );
    }

    public function test_receiving_converts_non_base_unit_to_base_quantity(): void
    {
        $user = $this->user();

        [
            $product,
            ,
            $purchase,
            $purchaseItem,
        ] = $this->purchaseFixture(
            'INV-RCV-002',
            12
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => 8,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,

                'quantity_base' => 96,
            ]
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'product_id' => $product->id,

                'source_quantity' => 8,

                'conversion_to_base' => 12,

                'quantity_delta_base' => 96,

                'quantity_before_base' => 0,

                'quantity_after_base' => 96,
            ]
        );
    }

    public function test_receiving_uses_actual_not_ordered_quantity(): void
    {
        $user = $this->user();

        [
            $product,
            ,
            $purchase,
            $purchaseItem,
        ] = $this->purchaseFixture(
            'INV-RCV-003',
            12,
            10
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => 6,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,

                'quantity_base' => 72,
            ]
        );

        $this->assertDatabaseMissing(
            'inventory_balances',
            [
                'product_id' => $product->id,

                'quantity_base' => 120,
            ]
        );
    }

    public function test_receiving_uses_purchase_conversion_snapshot(): void
    {
        $user = $this->user();

        [
            $product,
            $productUnit,
            $purchase,
            $purchaseItem,
        ] = $this->purchaseFixture(
            'INV-RCV-004',
            12
        );

        $productUnit->update([
            'conversion_to_base' => 24,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => 2,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,

                'quantity_base' => 24,
            ]
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'product_id' => $product->id,

                'source_quantity' => 2,

                'conversion_to_base' => 12,

                'quantity_delta_base' => 24,
            ]
        );
    }
}
