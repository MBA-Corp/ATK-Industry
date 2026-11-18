<?php

namespace Tests\Feature;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\Product;
use App\Support\CostMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_average_and_full_depletion_reconcile_without_float(): void
    {
        $product = Product::create(['sku' => 'COST-1', 'name' => 'Synthetic']);
        $action = app(ApplyStockMovementAction::class);
        $action->execute($product->id, 'OPENING', 'TEST', 1, '10', incomingValue: '10000');
        $action->execute($product->id, 'PURCHASE_RECEIPT', 'TEST', 2, '5', incomingValue: '10000');
        $sale = $action->execute($product->id, 'SALE', 'TEST', 3, '-3');
        $value = DB::table('inventory_valuations')->where('stock_movement_id', $sale->id)->first();
        $this->assertSame('-4000.000000', CostMath::round((string) $value->value_delta));
        $this->assertSame('16000.000000', CostMath::round((string) $value->value_after));
        $action->execute($product->id, 'SALE', 'TEST', 4, '-12');
        $this->assertSame('0.000000', CostMath::round((string) DB::table('inventory_costs')->value('inventory_value')));
    }

    public function test_unknown_opening_cost_is_not_silently_treated_as_zero(): void
    {
        $product = Product::create(['sku' => 'UNKNOWN', 'name' => 'Unknown']);
        $action = app(ApplyStockMovementAction::class);
        $action->execute($product->id, 'ADJUSTMENT', 'TEST', 1, '10');
        $action->execute($product->id, 'PURCHASE_RECEIPT', 'TEST', 2, '5', incomingValue: '10000');
        $action->execute($product->id, 'SALE', 'TEST', 3, '-3');
        $this->assertNull(DB::table('inventory_costs')->value('inventory_value'));
        $this->assertNull(DB::table('inventory_valuations')->orderByDesc('id')->value('value_delta'));
    }

    public function test_bad_acquisition_cost_rolls_back_stock_and_ledger(): void
    {
        $product = Product::create(['sku' => 'ROLLBACK', 'name' => 'Rollback']);
        try {
            app(ApplyStockMovementAction::class)->execute($product->id, 'OPENING', 'TEST', 1, '1', incomingValue: '-1');
            $this->fail('Negative cost accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertDatabaseCount('inventory_valuations', 0);
            $this->assertDatabaseCount('inventory_balances', 0);
        }
    }

    public function test_large_exact_cost_allocation_and_rounding(): void
    {
        $this->assertSame('3000000000000000.000000', CostMath::proportional('9000000000000000', '1', '3'));
        $this->assertSame('0.333333', CostMath::proportional('1', '1', '3'));
        $this->assertSame('1', CostMath::round('0.5', 0));
    }
}
