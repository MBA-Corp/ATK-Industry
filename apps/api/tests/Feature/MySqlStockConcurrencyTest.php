<?php

namespace Tests\Feature;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\Product;
use App\Support\CostMath;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlStockConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Real concurrent row-lock verification requires isolated MySQL.');
        }
        parent::setUp();
    }

    public function test_concurrent_stock_consumers_cannot_oversell_or_diverge_costs(): void
    {
        $product = Product::create(['sku' => 'CONCURRENT', 'name' => 'Synthetic concurrent stock']);
        app(ApplyStockMovementAction::class)->execute($product->id, 'OPENING', 'TEST', 1, '5', incomingValue: '5000');
        $first = new Process([PHP_BINARY, base_path('tests/Support/stock-worker.php'), (string) $product->id, '101']);
        $second = new Process([PHP_BINARY, base_path('tests/Support/stock-worker.php'), (string) $product->id, '102']);
        $first->start();
        $second->start();
        $codes = [$first->wait(), $second->wait()];
        sort($codes);
        $this->assertSame([0, 2], $codes, $first->getErrorOutput().$second->getErrorOutput());
        $this->assertSame('1.000000', (string) DB::table('inventory_balances')->value('quantity_base'));
        $this->assertSame('1000.000000', CostMath::round((string) DB::table('inventory_costs')->value('inventory_value')));
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('inventory_valuations', 2);
    }

    public function test_valuation_uses_current_cost_after_an_older_transaction_snapshot(): void
    {
        $existing = Product::create(['sku' => 'CURRENT-COST', 'name' => 'Existing value']);
        $new = Product::create(['sku' => 'FIRST-COST', 'name' => 'First value']);
        $apply = app(ApplyStockMovementAction::class);
        $apply->execute($existing->id, 'OPENING', 'REVIEW', 1, '10', incomingValue: '1000');

        config(['database.connections.cost_writer' => config('database.connections.mysql')]);
        $reader = DB::connection('mysql');
        $reader->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $reader->beginTransaction();
        try {
            $reader->table('inventory_costs')->count(); // Establish the reader's snapshot before the receipt.
            DB::setDefaultConnection('cost_writer');
            $apply->execute($existing->id, 'PURCHASE_RECEIPT', 'REVIEW', 2, '10', incomingValue: '2000');
            $apply->execute($new->id, 'PURCHASE_RECEIPT', 'REVIEW', 3, '10', incomingValue: '900');

            DB::setDefaultConnection('mysql');
            $sale = $apply->execute($existing->id, 'SALE', 'REVIEW', 4, '-10');
            $this->assertDatabaseHas('inventory_valuations', [
                'stock_movement_id' => $sale->id, 'value_before' => '3000.000000', 'value_delta' => '-1500.000000',
            ]);
            $firstSale = $apply->execute($new->id, 'SALE', 'REVIEW', 5, '-1');
            $reader->commit();
        } finally {
            DB::setDefaultConnection('mysql');
            if ($reader->transactionLevel() > 0) {
                $reader->rollBack();
            }
            DB::purge('cost_writer');
        }

        $this->assertDatabaseHas('inventory_costs', ['product_id' => $existing->id, 'inventory_value' => '1500.000000']);
        $this->assertDatabaseHas('inventory_valuations', [
            'stock_movement_id' => $firstSale->id, 'value_before' => '900.000000', 'value_delta' => '-90.000000',
        ]);
        $this->assertDatabaseHas('inventory_costs', ['product_id' => $new->id, 'inventory_value' => '810.000000']);
    }
}
