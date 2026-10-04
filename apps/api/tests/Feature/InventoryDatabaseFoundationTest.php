<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_tables_exist(): void
    {
        $this->assertTrue(
            Schema::hasTable(
                'inventory_balances'
            )
        );

        $this->assertTrue(
            Schema::hasTable(
                'stock_movements'
            )
        );
    }

    public function test_inventory_tables_have_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'inventory_balances',
                [
                    'id',
                    'product_id',
                    'quantity_base',
                    'created_at',
                    'updated_at',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'stock_movements',
                [
                    'id',
                    'product_id',
                    'product_unit_id',
                    'movement_type',
                    'source_type',
                    'source_id',
                    'source_quantity',
                    'conversion_to_base',
                    'quantity_delta_base',
                    'quantity_before_base',
                    'quantity_after_base',
                    'actor_user_id',
                    'terminal_id',
                    'reason',
                    'occurred_at',
                    'created_at',
                ]
            )
        );
    }

    public function test_product_can_only_have_one_inventory_balance(): void
    {
        $product = Product::create([
            'sku' => 'INV-001',
            'name' => 'Inventory Test',
        ]);

        DB::table(
            'inventory_balances'
        )->insert([
            'product_id' => $product->id,

            'quantity_base' => 10,

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'inventory_balances'
        )->insert([
            'product_id' => $product->id,

            'quantity_base' => 20,

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_stock_movement_source_must_be_unique(): void
    {
        $product = Product::create([
            'sku' => 'INV-002',
            'name' => 'Movement Test',
        ]);

        $movement = [
            'product_id' => $product->id,

            'movement_type' => 'PURCHASE_RECEIPT',

            'source_type' => 'PURCHASE_RECEIPT_ITEM',

            'source_id' => 1,

            'quantity_delta_base' => 10,

            'quantity_before_base' => 0,

            'quantity_after_base' => 10,

            'occurred_at' => now(),
            'created_at' => now(),
        ];

        DB::table(
            'stock_movements'
        )->insert(
            $movement
        );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'stock_movements'
        )->insert(
            $movement
        );
    }
}
