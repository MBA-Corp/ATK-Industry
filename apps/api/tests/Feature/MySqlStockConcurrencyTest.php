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
}
