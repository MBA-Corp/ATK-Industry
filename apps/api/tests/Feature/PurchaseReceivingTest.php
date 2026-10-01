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

class PurchaseReceivingTest extends TestCase
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

    private function supplier(
        string $code = 'SUP-001'
    ): Supplier {
        return Supplier::create([
            'code' => $code,
            'name' => "Supplier {$code}",
            'is_active' => true,
        ]);
    }

    private function purchaseItemData(
        Supplier $supplier,
        string $sku,
        string $unitCode,
        int $price = 5000
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
                'conversion_to_base' => 1,
                'selling_price' => $price + 2000,
                'is_base' => true,
                'is_active' => true,
            ]);

        $productSupplier = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,
                'last_purchase_price' => $price,
                'is_active' => true,
            ]);

        return [
            $productUnit,
            $productSupplier,
        ];
    }

    private function purchaseWithOneItem(
        Supplier $supplier,
        string $number = 'PUR-001',
        float $qtyOrdered = 10
    ): array {
        [
            $productUnit,
            $productSupplier,
        ] = $this->purchaseItemData(
            $supplier,
            "SKU-{$number}",
            "UNIT-{$number}"
        );

        $purchase = Purchase::create([
            'purchase_number' => $number,
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-10-01',
            'status' => 'DRAFT',
            'subtotal' => 50000,
            'grand_total' => 50000,
        ]);

        $purchaseItem = $purchase
            ->items()
            ->create([
                'product_supplier_id' => $productSupplier->id,

                'product_unit_id' => $productUnit->id,

                'qty_ordered' => $qtyOrdered,

                'qty_received' => 0,

                'purchase_price' => 5000,

                'line_total' => (int) round(
                    $qtyOrdered * 5000
                ),
            ]);

        return [
            $purchase,
            $purchaseItem,
        ];
    }

    public function test_purchase_can_be_received_with_actual_quantity(): void
    {
        $user = $this->userWithPermission(
            'purchase.receive'
        );

        $supplier = $this->supplier();

        [
            $purchase,
            $purchaseItem,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-001',
            10
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'received_at' => '2026-10-01 15:30:00',

                    'notes' => 'Barang diterima dan dicek.',

                    'items' => [
                        [
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => 8,
                        ],
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'RECEIVED'
            );

        $this->assertDatabaseHas(
            'purchases',
            [
                'id' => $purchase->id,
                'status' => 'RECEIVED',
            ]
        );

        $this->assertDatabaseHas(
            'purchase_items',
            [
                'id' => $purchaseItem->id,
                'qty_received' => 8,
            ]
        );

        $this->assertDatabaseHas(
            'purchase_receipts',
            [
                'purchase_id' => $purchase->id,

                'received_by' => $user->id,

                'notes' => 'Barang diterima dan dicek.',
            ]
        );

        $receiptId = $purchase
            ->receipt()
            ->value('id');

        $this->assertDatabaseHas(
            'purchase_receipt_items',
            [
                'purchase_receipt_id' => $receiptId,

                'purchase_item_id' => $purchaseItem->id,

                'qty_received' => 8,
            ]
        );
    }

    public function test_received_quantity_may_exceed_ordered_quantity(): void
    {
        $user = $this->userWithPermission(
            'purchase.receive'
        );

        $supplier = $this->supplier();

        [
            $purchase,
            $purchaseItem,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-002',
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

                            'qty_received' => 12,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'purchase_items',
            [
                'id' => $purchaseItem->id,
                'qty_received' => 12,
            ]
        );
    }

    public function test_zero_received_quantity_is_allowed(): void
    {
        $user = $this->userWithPermission(
            'purchase.receive'
        );

        $supplier = $this->supplier();

        [
            $purchase,
            $purchaseItem,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-003'
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => 0,
                        ],
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'RECEIVED'
            );

        $this->assertDatabaseHas(
            'purchase_items',
            [
                'id' => $purchaseItem->id,
                'qty_received' => 0,
            ]
        );
    }

    public function test_receiving_must_include_every_purchase_item(): void
    {
        $user = $this->userWithPermission(
            'purchase.receive'
        );

        $supplier = $this->supplier();

        [
            $purchase,
            $firstItem,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-004'
        );

        [
            $productUnit,
            $productSupplier,
        ] = $this->purchaseItemData(
            $supplier,
            'SKU-SECOND',
            'UNIT-SECOND'
        );

        $purchase
            ->items()
            ->create([
                'product_supplier_id' => $productSupplier->id,

                'product_unit_id' => $productUnit->id,

                'qty_ordered' => 5,
                'qty_received' => 0,
                'purchase_price' => 5000,
                'line_total' => 25000,
            ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $firstItem->id,

                            'qty_received' => 5,
                        ],
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items'
            );

        $this->assertDatabaseCount(
            'purchase_receipts',
            0
        );

        $this->assertDatabaseHas(
            'purchases',
            [
                'id' => $purchase->id,
                'status' => 'DRAFT',
            ]
        );
    }

    public function test_item_from_another_purchase_cannot_be_received(): void
    {
        $user = $this->userWithPermission(
            'purchase.receive'
        );

        $supplier = $this->supplier();

        [
            $purchaseA,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-005'
        );

        [
            ,
            $itemB,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-006'
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchaseA->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $itemB->id,

                            'qty_received' => 1,
                        ],
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items'
            );

        $this->assertDatabaseCount(
            'purchase_receipts',
            0
        );
    }

    public function test_purchase_cannot_be_received_twice(): void
    {
        $user = $this->userWithPermission(
            'purchase.receive'
        );

        $supplier = $this->supplier();

        [
            $purchase,
            $purchaseItem,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-007'
        );

        $payload = [
            'items' => [
                [
                    'purchase_item_id' => $purchaseItem->id,

                    'qty_received' => 10,
                ],
            ],
        ];

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                $payload
            )
            ->assertOk();

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'purchase'
            );

        $this->assertDatabaseCount(
            'purchase_receipts',
            1
        );
    }

    public function test_user_without_permission_cannot_receive_purchase(): void
    {
        $user = User::factory()->create();

        $supplier = $this->supplier();

        [
            $purchase,
            $purchaseItem,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-008'
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => 10,
                        ],
                    ],
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'purchase_receipts',
            0
        );
    }

    public function test_guest_cannot_receive_purchase(): void
    {
        $supplier = $this->supplier();

        [
            $purchase,
            $purchaseItem,
        ] = $this->purchaseWithOneItem(
            $supplier,
            'PUR-009'
        );

        $this
            ->postJson(
                "/api/v1/purchases/{$purchase->id}/receive",
                [
                    'items' => [
                        [
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => 10,
                        ],
                    ],
                ]
            )
            ->assertUnauthorized();

        $this->assertDatabaseCount(
            'purchase_receipts',
            0
        );
    }
}
