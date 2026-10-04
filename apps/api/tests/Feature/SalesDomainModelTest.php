<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_belongs_to_terminal_and_cashier(): void
    {
        $user = User::factory()->create();

        $terminal = Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS 01',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_number' => 'SALE-TEST-001',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'sold_at' => now(),
        ]);

        $this->assertTrue(
            $sale->terminal->is($terminal)
        );

        $this->assertTrue(
            $sale->cashier->is($user)
        );
    }

    public function test_sale_has_items(): void
    {
        $user = User::factory()->create();

        $terminal = Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS 02',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'SALE-001',
            'name' => 'Pulpen Test',
            'is_active' => true,
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 5000,
            'is_base' => true,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_number' => 'SALE-TEST-002',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'sold_at' => now(),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'unit_code' => $unit->code,
            'quantity' => 2,
            'conversion_to_base' => 1,
            'catalog_unit_price' => 5000,
            'sale_unit_price' => 5000,
            'line_total' => 10000,
        ]);

        $this->assertCount(
            1,
            $sale->items
        );

        $this->assertTrue(
            $sale->items->first()->product->is(
                $product
            )
        );

        $this->assertTrue(
            $sale->items
                ->first()
                ->productUnit
                ->is($productUnit)
        );
    }

    public function test_sale_decimal_and_integer_casts_work(): void
    {
        $user = User::factory()->create();

        $terminal = Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS 03',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'code' => 'BOX',
            'name' => 'Box',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'SALE-002',
            'name' => 'Box Test',
            'is_active' => true,
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 12,
            'selling_price' => 24000,
            'is_base' => false,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_number' => 'SALE-TEST-003',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'subtotal' => 24000,
            'grand_total' => 24000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'unit_code' => $unit->code,
            'quantity' => 1,
            'conversion_to_base' => 12,
            'catalog_unit_price' => 24000,
            'sale_unit_price' => 24000,
            'line_total' => 24000,
        ]);

        $this->assertSame(
            24000,
            $sale->subtotal
        );

        $this->assertSame(
            24000,
            $sale->grand_total
        );

        $this->assertSame(
            '1.000000',
            $item->quantity
        );

        $this->assertSame(
            '12.000000',
            $item->conversion_to_base
        );

        $this->assertSame(
            24000,
            $item->sale_unit_price
        );
    }

    public function test_sale_item_keeps_historical_snapshots_when_master_data_changes(): void
    {
        $user = User::factory()->create();

        $terminal = Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS Snapshot',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'SALE-SNAPSHOT-001',
            'name' => 'Pulpen Snapshot',
            'is_active' => true,
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 12,
            'selling_price' => 24000,
            'is_base' => false,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_number' => 'SALE-SNAPSHOT-001',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'subtotal' => 24000,
            'grand_total' => 24000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'unit_code' => $unit->code,
            'quantity' => 1,
            'conversion_to_base' => $productUnit->conversion_to_base,
            'catalog_unit_price' => $productUnit->selling_price,
            'sale_unit_price' => $productUnit->selling_price,
            'line_total' => 24000,
        ]);

        $product->update([
            'sku' => 'SALE-SNAPSHOT-CHANGED',
            'name' => 'Nama Baru',
        ]);

        $unit->update([
            'code' => 'BOX',
            'name' => 'Box',
        ]);

        $productUnit->update([
            'conversion_to_base' => 24,
            'selling_price' => 48000,
        ]);

        $item->refresh();

        $this->assertSame(
            'SALE-SNAPSHOT-001',
            $item->product_sku
        );

        $this->assertSame(
            'Pulpen Snapshot',
            $item->product_name
        );

        $this->assertSame(
            'PACK',
            $item->unit_code
        );

        $this->assertSame(
            '12.000000',
            $item->conversion_to_base
        );

        $this->assertSame(
            24000,
            $item->catalog_unit_price
        );

        $this->assertSame(
            24000,
            $item->sale_unit_price
        );
    }
}
