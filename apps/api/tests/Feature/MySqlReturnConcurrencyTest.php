<?php

namespace Tests\Feature;

use App\Actions\Sales\ReturnSaleAction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\RetailFixture;
use Tests\TestCase;

class MySqlReturnConcurrencyTest extends TestCase
{
    use DatabaseMigrations, RetailFixture;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Concurrent return/refund verification requires isolated MySQL.');
        }
        parent::setUp();
    }

    private function compete(string $operation, int $id, int $actor, array $data): void
    {
        $processes = [];
        foreach ([1, 2] as $unused) {
            $data['idempotency_key'] = (string) Str::uuid();
            $process = new Process([PHP_BINARY, base_path('tests/Support/return-worker.php'),
                $operation, (string) $id, json_encode($data, JSON_THROW_ON_ERROR), (string) $actor]);
            $process->start();
            $processes[] = $process;
        }
        $codes = array_map(fn ($process) => $process->wait(), $processes);
        sort($codes);
        $this->assertSame([0, 2], $codes, implode('', array_map(fn ($p) => $p->getErrorOutput(), $processes)));
    }

    public function test_concurrent_returns_cannot_exceed_sold_quantity(): void
    {
        [$user, $product, $pu, $sale] = $this->retailFixture();
        $this->compete('return', $sale->id, $user->id, ['reason' => 'Synthetic concurrency',
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => '2', 'condition' => 'resellable']]]);
        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertSame('2.000000', (string) DB::table('sale_return_items')->value('quantity'));
        $this->assertSame('9.000000', (string) DB::table('inventory_balances')->value('quantity_base'));
    }

    public function test_concurrent_manual_refunds_record_only_once(): void
    {
        [$user, $product, $pu, $sale] = $this->retailFixture();
        $id = app(ReturnSaleAction::class)->execute($sale->id, [
            'idempotency_key' => (string) Str::uuid(), 'reason' => 'Synthetic',
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => '1', 'condition' => 'resellable']],
        ], $user->id);
        $this->compete('refund', $id, $user->id, ['amount' => 101, 'method' => 'CASH',
            'reference' => 'Synthetic evidence', 'confirm_record_only' => true]);
        $this->assertDatabaseCount('sale_refunds', 1);
    }
}
