<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_inventory_balance(): void
    {
        $product = Product::create([
            'sku' => 'INV-REL-001',
            'name' => 'Inventory Relation',
        ]);

        $balance = $product
            ->inventoryBalance()
            ->create([
                'quantity_base' => 15,
            ]);

        $this->assertInstanceOf(
            InventoryBalance::class,
            $product
                ->inventoryBalance
        );

        $this->assertSame(
            $balance->id,
            $product
                ->inventoryBalance
                ->id
        );
    }

    public function test_product_has_stock_movements(): void
    {
        $product = Product::create([
            'sku' => 'INV-REL-002',
            'name' => 'Stock Movement Relation',
        ]);

        $movement = $product
            ->stockMovements()
            ->create([
                'movement_type' => 'ADJUSTMENT',

                'source_type' => 'TEST_ADJUSTMENT',

                'source_id' => 1,

                'quantity_delta_base' => 5,

                'quantity_before_base' => 0,

                'quantity_after_base' => 5,

                'occurred_at' => now(),
            ]);

        $this->assertInstanceOf(
            StockMovement::class,
            $movement
        );

        $this->assertTrue(
            $product
                ->stockMovements
                ->contains(
                    $movement
                )
        );
    }

    public function test_inventory_decimal_casts_work(): void
    {
        $product = Product::create([
            'sku' => 'INV-REL-003',
            'name' => 'Decimal Test',
        ]);

        $balance = $product
            ->inventoryBalance()
            ->create([
                'quantity_base' => '12.500000',
            ]);

        $this->assertSame(
            '12.500000',
            $balance->quantity_base
        );

        $movement = $product
            ->stockMovements()
            ->create([
                'movement_type' => 'ADJUSTMENT',

                'source_type' => 'DECIMAL_TEST',

                'source_id' => 1,

                'source_quantity' => '1.250000',

                'conversion_to_base' => '10.000000',

                'quantity_delta_base' => '12.500000',

                'quantity_before_base' => '0.000000',

                'quantity_after_base' => '12.500000',

                'occurred_at' => now(),
            ]);

        $this->assertSame(
            '12.500000',
            $movement
                ->quantity_delta_base
        );
    }
}
