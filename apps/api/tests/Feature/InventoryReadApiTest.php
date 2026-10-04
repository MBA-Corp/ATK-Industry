<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReadApiTest extends TestCase
{
    use RefreshDatabase;

    private function product(
        string $sku,
        string $name,
        bool $active = true
    ): array {
        $product = Product::create([
            'sku' => $sku,
            'name' => $name,
            'is_active' => $active,
        ]);

        $unit = Unit::create([
            'code' => "U-{$sku}",
            'name' => "Unit {$sku}",
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

        return [
            $product,
            $productUnit,
        ];
    }

    public function test_authenticated_user_can_list_inventory(): void
    {
        $user = User::factory()->create();

        [$product] = $this->product(
            'INV-001',
            'Kertas A4'
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 25,
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonPath(
                'data.0.product.sku',
                'INV-001'
            )
            ->assertJsonPath(
                'data.0.quantity_base',
                '25.000000'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_product_without_balance_returns_zero_stock(): void
    {
        $user = User::factory()->create();

        $this->product(
            'INV-002',
            'Pulpen'
        );

        $this
            ->actingAs($user)
            ->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonPath(
                'data.0.quantity_base',
                '0.000000'
            );
    }

    public function test_inventory_returns_base_unit(): void
    {
        $user = User::factory()->create();

        [$product] = $this->product(
            'INV-003',
            'Pensil'
        );

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/inventory/{$product->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.base_unit.code',
                'U-INV-003'
            )
            ->assertJsonPath(
                'data.base_unit.name',
                'Unit INV-003'
            );
    }

    public function test_inventory_can_be_searched_by_sku(): void
    {
        $user = User::factory()->create();

        $this->product(
            'SEARCH-001',
            'Buku Tulis'
        );

        $this->product(
            'OTHER-001',
            'Map Plastik'
        );

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/inventory?search=SEARCH-001'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.product.sku',
                'SEARCH-001'
            );
    }

    public function test_inventory_can_be_searched_by_name(): void
    {
        $user = User::factory()->create();

        $this->product(
            'INV-004',
            'Spidol Permanen'
        );

        $this->product(
            'INV-005',
            'Penghapus'
        );

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/inventory?search=Spidol'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.product.name',
                'Spidol Permanen'
            );
    }

    public function test_inventory_can_be_filtered_by_active_status(): void
    {
        $user = User::factory()->create();

        $this->product(
            'INV-006',
            'Active Product',
            true
        );

        $this->product(
            'INV-007',
            'Inactive Product',
            false
        );

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/inventory?is_active=false'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.product.sku',
                'INV-007'
            );
    }

    public function test_authenticated_user_can_get_inventory_detail(): void
    {
        $user = User::factory()->create();

        [$product] = $this->product(
            'INV-008',
            'Binder'
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 15,
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/inventory/{$product->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.product.id',
                $product->id
            )
            ->assertJsonPath(
                'data.quantity_base',
                '15.000000'
            );
    }

    public function test_stock_movements_are_returned_latest_first(): void
    {
        $user = User::factory()->create();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'INV-009',
            'Movement Product'
        );

        StockMovement::create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'movement_type' => 'ADJUSTMENT',
            'source_type' => 'TEST',
            'source_id' => 1,
            'quantity_delta_base' => 5,
            'quantity_before_base' => 0,
            'quantity_after_base' => 5,
            'occurred_at' => '2026-09-30 10:00:00',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'movement_type' => 'PURCHASE_RECEIPT',
            'source_type' => 'TEST',
            'source_id' => 2,
            'quantity_delta_base' => 10,
            'quantity_before_base' => 5,
            'quantity_after_base' => 15,
            'occurred_at' => '2026-10-01 10:00:00',
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/inventory/{$product->id}/movements"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.0.movement_type',
                'PURCHASE_RECEIPT'
            )
            ->assertJsonPath(
                'data.1.movement_type',
                'ADJUSTMENT'
            );
    }

    public function test_stock_movements_can_be_filtered_by_type(): void
    {
        $user = User::factory()->create();

        [$product] = $this->product(
            'INV-010',
            'Filter Product'
        );

        StockMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'PURCHASE_RECEIPT',
            'source_type' => 'TEST',
            'source_id' => 1,
            'quantity_delta_base' => 10,
            'quantity_before_base' => 0,
            'quantity_after_base' => 10,
            'occurred_at' => '2026-10-01 08:00:00',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'ADJUSTMENT',
            'source_type' => 'TEST',
            'source_id' => 2,
            'quantity_delta_base' => 5,
            'quantity_before_base' => 10,
            'quantity_after_base' => 15,
            'occurred_at' => '2026-10-01 09:00:00',
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/inventory/{$product->id}/movements?movement_type=PURCHASE_RECEIPT"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.movement_type',
                'PURCHASE_RECEIPT'
            );
    }

    public function test_stock_movements_can_be_filtered_by_date(): void
    {
        $user = User::factory()->create();

        [$product] = $this->product(
            'INV-011',
            'Date Filter'
        );

        StockMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'ADJUSTMENT',
            'source_type' => 'TEST',
            'source_id' => 1,
            'quantity_delta_base' => 5,
            'quantity_before_base' => 0,
            'quantity_after_base' => 5,
            'occurred_at' => '2026-09-30 08:00:00',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'ADJUSTMENT',
            'source_type' => 'TEST',
            'source_id' => 2,
            'quantity_delta_base' => 5,
            'quantity_before_base' => 5,
            'quantity_after_base' => 10,
            'occurred_at' => '2026-10-01 08:00:00',
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/inventory/{$product->id}/movements"
                .'?date_from=2026-10-01 00:00:00'
                .'&date_to=2026-10-01 23:59:59'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.source.id',
                2
            );
    }

    public function test_missing_inventory_product_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/inventory/999999'
            )
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/inventory/999999/movements'
            )
            ->assertNotFound();
    }

    public function test_guest_cannot_access_inventory_endpoints(): void
    {
        [$product] = $this->product(
            'INV-012',
            'Guest Product'
        );

        $this
            ->getJson(
                '/api/v1/inventory'
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                "/api/v1/inventory/{$product->id}"
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                "/api/v1/inventory/{$product->id}/movements"
            )
            ->assertUnauthorized();
    }
}
