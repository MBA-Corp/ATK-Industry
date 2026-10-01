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

class PurchaseApiTest extends TestCase
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
        string $code = 'SUP-001',
        bool $active = true
    ): Supplier {
        return Supplier::create([
            'code' => $code,
            'name' => "Supplier {$code}",
            'is_active' => $active,
        ]);
    }

    private function productSupplier(
        Supplier $supplier,
        string $sku,
        string $unitCode
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
                'selling_price' => 10000,
                'is_base' => true,
                'is_active' => true,
            ]);

        $productSupplier = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,
                'last_purchase_price' => 5000,
                'is_active' => true,
            ]);

        return [
            $product,
            $productUnit,
            $productSupplier,
        ];
    }

    public function test_user_with_permission_can_create_purchase(): void
    {
        $user = $this->userWithPermission(
            'purchase.create'
        );

        $supplier = $this->supplier();

        [
            $product,
            $productUnit,
            $productSupplier,
        ] = $this->productSupplier(
            $supplier,
            'ATK-001',
            'PCS'
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplier->id,

                'supplier_invoice_number' => 'INV-001',

                'purchase_date' => '2026-10-01',

                'operational_cost' => 1500,

                'notes' => 'Pembelian test',

                'items' => [
                    [
                        'product_supplier_id' => $productSupplier->id,

                        'product_unit_id' => $productUnit->id,

                        'qty_ordered' => 2,

                        'purchase_price' => 5000,
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.supplier.id',
                $supplier->id
            )
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.subtotal',
                10000
            )
            ->assertJsonPath(
                'data.operational_cost',
                1500
            )
            ->assertJsonPath(
                'data.grand_total',
                11500
            )
            ->assertJsonPath(
                'data.created_by.id',
                $user->id
            )
            ->assertJsonPath(
                'data.items.0.product.sku',
                $product->sku
            )
            ->assertJsonPath(
                'data.items.0.purchase_price',
                5000
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                10000
            );

        $purchase = Purchase::query()
            ->firstOrFail();

        $this->assertDatabaseHas(
            'purchases',
            [
                'id' => $purchase->id,
                'supplier_id' => $supplier->id,
                'supplier_invoice_number' => 'INV-001',
                'status' => 'DRAFT',
                'subtotal' => 10000,
                'operational_cost' => 1500,
                'grand_total' => 11500,
                'created_by' => $user->id,
            ]
        );

        $this->assertDatabaseHas(
            'purchase_items',
            [
                'purchase_id' => $purchase->id,

                'product_supplier_id' => $productSupplier->id,

                'product_unit_id' => $productUnit->id,

                'purchase_price' => 5000,
                'line_total' => 10000,
            ]
        );
    }

    public function test_purchases_can_be_filtered(): void
    {
        $user = User::factory()->create();

        $supplierA = $this->supplier(
            'SUP-001'
        );

        $supplierB = $this->supplier(
            'SUP-002'
        );

        Purchase::create([
            'purchase_number' => 'PUR-001',

            'supplier_id' => $supplierA->id,

            'purchase_date' => '2026-09-30',

            'status' => 'DRAFT',
        ]);

        Purchase::create([
            'purchase_number' => 'PUR-002',

            'supplier_id' => $supplierA->id,

            'purchase_date' => '2026-10-01',

            'status' => 'DRAFT',
        ]);

        Purchase::create([
            'purchase_number' => 'PUR-003',

            'supplier_id' => $supplierB->id,

            'purchase_date' => '2026-10-01',

            'status' => 'RECEIVED',
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/purchases'
                .'?supplier_id='
                .$supplierA->id
                .'&status=DRAFT'
                .'&date_from=2026-10-01'
                .'&date_to=2026-10-01'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.purchase_number',
                'PUR-002'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_authenticated_user_can_get_purchase_detail(): void
    {
        $user = User::factory()->create();

        $supplier = $this->supplier();

        [
            $product,
            $productUnit,
            $productSupplier,
        ] = $this->productSupplier(
            $supplier,
            'ATK-DETAIL',
            'PACK'
        );

        $purchase = Purchase::create([
            'purchase_number' => 'PUR-DETAIL',

            'supplier_id' => $supplier->id,

            'supplier_invoice_number' => 'INV-DETAIL',

            'purchase_date' => '2026-10-01',

            'subtotal' => 12000,
            'grand_total' => 12000,
            'created_by' => $user->id,
        ]);

        $purchase->items()->create([
            'product_supplier_id' => $productSupplier->id,

            'product_unit_id' => $productUnit->id,

            'qty_ordered' => 2,
            'qty_received' => 0,
            'purchase_price' => 6000,
            'line_total' => 12000,
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/purchases/{$purchase->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.purchase_number',
                'PUR-DETAIL'
            )
            ->assertJsonPath(
                'data.supplier.code',
                'SUP-001'
            )
            ->assertJsonPath(
                'data.items.0.product.sku',
                $product->sku
            )
            ->assertJsonPath(
                'data.items.0.product_unit.unit.code',
                'PACK'
            )
            ->assertJsonPath(
                'data.items.0.purchase_price',
                6000
            );
    }

    public function test_user_without_permission_cannot_create_purchase(): void
    {
        $user = User::factory()->create();

        $supplier = $this->supplier();

        [
            ,
            $productUnit,
            $productSupplier,
        ] = $this->productSupplier(
            $supplier,
            'ATK-002',
            'PCS'
        );

        $this
            ->actingAs($user)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplier->id,

                'purchase_date' => '2026-10-01',

                'items' => [
                    [
                        'product_supplier_id' => $productSupplier->id,

                        'product_unit_id' => $productUnit->id,

                        'qty_ordered' => 1,
                        'purchase_price' => 5000,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount(
            'purchases',
            0
        );
    }

    public function test_inactive_supplier_cannot_be_used_for_purchase(): void
    {
        $user = $this->userWithPermission(
            'purchase.create'
        );

        $supplier = $this->supplier(
            'SUP-INACTIVE',
            false
        );

        [
            ,
            $productUnit,
            $productSupplier,
        ] = $this->productSupplier(
            $supplier,
            'ATK-003',
            'BOX'
        );

        $this
            ->actingAs($user)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplier->id,

                'purchase_date' => '2026-10-01',

                'items' => [
                    [
                        'product_supplier_id' => $productSupplier->id,

                        'product_unit_id' => $productUnit->id,

                        'qty_ordered' => 1,
                        'purchase_price' => 5000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'supplier_id'
            );

        $this->assertDatabaseCount(
            'purchases',
            0
        );
    }

    public function test_product_supplier_must_belong_to_selected_supplier(): void
    {
        $user = $this->userWithPermission(
            'purchase.create'
        );

        $supplierA = $this->supplier(
            'SUP-A'
        );

        $supplierB = $this->supplier(
            'SUP-B'
        );

        [
            ,
            $productUnit,
            $productSupplier,
        ] = $this->productSupplier(
            $supplierB,
            'ATK-004',
            'REAM'
        );

        $this
            ->actingAs($user)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierA->id,

                'purchase_date' => '2026-10-01',

                'items' => [
                    [
                        'product_supplier_id' => $productSupplier->id,

                        'product_unit_id' => $productUnit->id,

                        'qty_ordered' => 1,
                        'purchase_price' => 7000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.product_supplier_id'
            );

        $this->assertDatabaseCount(
            'purchases',
            0
        );
    }

    public function test_product_unit_must_belong_to_product_supplier_product(): void
    {
        $user = $this->userWithPermission(
            'purchase.create'
        );

        $supplier = $this->supplier();

        [
            ,
            ,
            $productSupplierA,
        ] = $this->productSupplier(
            $supplier,
            'ATK-005',
            'PCS-A'
        );

        [
            ,
            $productUnitB,
        ] = $this->productSupplier(
            $supplier,
            'ATK-006',
            'PCS-B'
        );

        $this
            ->actingAs($user)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplier->id,

                'purchase_date' => '2026-10-01',

                'items' => [
                    [
                        'product_supplier_id' => $productSupplierA->id,

                        'product_unit_id' => $productUnitB->id,

                        'qty_ordered' => 1,
                        'purchase_price' => 5000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.product_unit_id'
            );

        $this->assertDatabaseCount(
            'purchases',
            0
        );
    }

    public function test_duplicate_supplier_invoice_returns_conflict(): void
    {
        $user = $this->userWithPermission(
            'purchase.create'
        );

        $supplier = $this->supplier();

        [
            ,
            $productUnit,
            $productSupplier,
        ] = $this->productSupplier(
            $supplier,
            'ATK-007',
            'PCS'
        );

        Purchase::create([
            'purchase_number' => 'PUR-EXISTING',

            'supplier_id' => $supplier->id,

            'supplier_invoice_number' => 'INV-DUPLICATE',

            'purchase_date' => '2026-09-30',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplier->id,

                'supplier_invoice_number' => 'INV-DUPLICATE',

                'purchase_date' => '2026-10-01',

                'items' => [
                    [
                        'product_supplier_id' => $productSupplier->id,

                        'product_unit_id' => $productUnit->id,

                        'qty_ordered' => 1,
                        'purchase_price' => 5000,
                    ],
                ],
            ])
            ->assertConflict();

        $this->assertDatabaseCount(
            'purchases',
            1
        );
    }

    public function test_invalid_item_prevents_purchase_creation(): void
    {
        $user = $this->userWithPermission(
            'purchase.create'
        );

        $supplier = $this->supplier(
            'SUP-VALID'
        );

        $otherSupplier = $this->supplier(
            'SUP-OTHER'
        );

        [
            ,
            $validUnit,
            $validProductSupplier,
        ] = $this->productSupplier(
            $supplier,
            'ATK-008',
            'UNIT-A'
        );

        [
            ,
            $invalidUnit,
            $invalidProductSupplier,
        ] = $this->productSupplier(
            $otherSupplier,
            'ATK-009',
            'UNIT-B'
        );

        $this
            ->actingAs($user)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplier->id,

                'purchase_date' => '2026-10-01',

                'items' => [
                    [
                        'product_supplier_id' => $validProductSupplier
                            ->id,

                        'product_unit_id' => $validUnit->id,

                        'qty_ordered' => 2,
                        'purchase_price' => 4000,
                    ],
                    [
                        'product_supplier_id' => $invalidProductSupplier
                            ->id,

                        'product_unit_id' => $invalidUnit->id,

                        'qty_ordered' => 1,
                        'purchase_price' => 8000,
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'purchases',
            0
        );

        $this->assertDatabaseCount(
            'purchase_items',
            0
        );
    }

    public function test_guest_cannot_access_purchase_endpoints(): void
    {
        $supplier = $this->supplier();

        $purchase = Purchase::create([
            'purchase_number' => 'PUR-GUEST',

            'supplier_id' => $supplier->id,

            'purchase_date' => '2026-10-01',
        ]);

        $this
            ->getJson('/api/v1/purchases')
            ->assertUnauthorized();

        $this
            ->getJson(
                "/api/v1/purchases/{$purchase->id}"
            )
            ->assertUnauthorized();

        $this
            ->postJson(
                '/api/v1/purchases',
                []
            )
            ->assertUnauthorized();
    }
}
