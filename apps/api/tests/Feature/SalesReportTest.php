<?php

namespace Tests\Feature;

use App\Actions\Sales\CreateSaleAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RetailFixture;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase, RetailFixture;

    private const PATH = '/api/v1/reports/sales';

    private const RANGE = '?date_from=2026-11-26&date_to=2026-11-26';

    public function test_jakarta_day_counts_transactions_independently_of_lines_and_uses_snapshot_units(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0, 0));
        [$owner, $product, $unit, $sale] = $this->retailFixture('1.5', 100);
        $box = $product->productUnits()->create([
            'unit_id' => DB::table('units')->insertGetId(['code' => 'BOX', 'name' => 'Box', 'is_active' => true]),
            'conversion_to_base' => '2.5', 'selling_price' => 60, 'is_base' => false, 'is_active' => true,
        ]);
        $this->sell($owner, $sale->terminal_id, [
            ['product_unit_id' => $unit->id, 'quantity' => '0.1'],
            ['product_unit_id' => $box->id, 'quantity' => '0.5'],
        ], 40, '2026-11-26 16:59:59');
        $this->sell($owner, $sale->terminal_id, [['product_unit_id' => $unit->id, 'quantity' => '1']], 100, '2026-11-26 17:00:00');
        $this->sell($owner, $sale->terminal_id, [['product_unit_id' => $unit->id, 'quantity' => '1']], 100, '2026-11-25 16:59:59');
        $product->update(['sku' => 'RENAMED']);
        $box->update(['conversion_to_base' => '9']);

        $this->actingAs($owner)->getJson(self::PATH.self::RANGE.'&group_by=day')
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.report_date', '2026-11-26')
            ->assertJsonPath('data.0.transaction_count', 2)
            ->assertJsonPath('data.0.sales_line_count', 3)
            ->assertJsonPath('data.0.gross_sales', '190');
        $this->getJson(self::PATH.self::RANGE.'&product_sku=SYNTHETIC-RETAIL')
            ->assertOk()->assertJsonPath('data.0.sold_quantity_base', '2.850000');
        $this->getJson(self::PATH.self::RANGE.'&group_by=unit&unit_code=BOX')
            ->assertOk()->assertJsonPath('data.0.product_sku', 'SYNTHETIC-RETAIL')
            ->assertJsonPath('data.0.unit_code', 'BOX')
            ->assertJsonPath('data.0.conversion_to_base', '2.500000')
            ->assertJsonPath('data.0.sold_quantity', '0.500000')
            ->assertJsonPath('data.0.sold_quantity_base', '1.250000');
        $this->getJson(self::PATH.self::RANGE.'&group_by=unit&per_page=1&sort=net_sales&direction=asc')
            ->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.unit_code', 'BOX');
        $this->getJson(self::PATH.self::RANGE.'&group_by=unit&per_page=1&sort=net_sales&direction=asc&page=2')
            ->assertOk()->assertJsonPath('data.0.unit_code', 'PCS');
    }

    public function test_returns_follow_effective_date_and_manual_refund_does_not_reduce_sales_again(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(16, 0, 0));
        [$owner, , , $sale] = $this->retailFixture();
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0, 0));
        $this->actingAs($owner);
        $ret = $this->postJson("/api/v1/sales/{$sale->id}/returns", [
            'idempotency_key' => (string) Str::uuid(), 'reason' => 'Returns runner',
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => '1', 'condition' => 'damaged']],
        ])->assertOk()->json('data.id');
        $this->postJson("/api/v1/sale-returns/$ret/refund", [
            'idempotency_key' => (string) Str::uuid(), 'amount' => 101, 'method' => 'CASH',
            'reference' => 'Synthetic refund', 'confirm_record_only' => true,
        ])->assertOk();

        $this->getJson(self::PATH.'?date_from=2026-11-25&date_to=2026-11-25')
            ->assertOk()->assertJsonPath('data.0.net_sales', '303');
        $this->getJson(self::PATH.self::RANGE)
            ->assertOk()->assertJsonPath('data.0.transaction_count', 0)
            ->assertJsonPath('data.0.sales_returns', '101')
            ->assertJsonPath('data.0.net_sales', '-101')
            ->assertJsonPath('data.0.net_quantity_base', '-1.000000');
    }

    public function test_cancelled_sale_reconciles_on_original_day_and_cannot_enter_net_sales(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0, 0));
        [$owner, , , $sale] = $this->retailFixture();
        $this->travel(2)->days();
        $this->actingAs($owner)->postJson("/api/v1/sales/{$sale->id}/cancel", ['reason' => 'Synthetic cancellation'])->assertOk();

        $this->getJson(self::PATH.self::RANGE)
            ->assertOk()->assertJsonPath('data.0.transaction_count', 0)
            ->assertJsonPath('data.0.cancelled_transaction_count', 1)
            ->assertJsonPath('data.0.gross_sales', '303')
            ->assertJsonPath('data.0.cancellations', '303')
            ->assertJsonPath('data.0.net_sales', '0')
            ->assertJsonPath('data.0.cancelled_quantity_base', '3.000000')
            ->assertJsonPath('data.0.net_quantity_base', '0.000000');
    }

    public function test_csv_uses_report_filters_and_escapes_snapshot_sku_and_validates_access(): void
    {
        $this->getJson(self::PATH.self::RANGE)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::PATH.self::RANGE)->assertForbidden();
        $this->getJson(self::PATH.'/export'.self::RANGE)->assertForbidden();
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0, 0));
        [$owner, , , $sale] = $this->retailFixture();
        DB::table('sale_items')->where('sale_id', $sale->id)->update(['product_sku' => '=SYNTHETIC']);
        $this->actingAs($owner)->getJson(self::PATH.self::RANGE.'&sort=amount')->assertUnprocessable();
        $this->getJson(self::PATH.'?date_from=2025-01-01&date_to=2026-11-26')->assertUnprocessable();
        $response = $this->get(self::PATH.'/export'.self::RANGE.'&unit_code=PCS&page=2')
            ->assertOk()->assertDownload('sales-report.csv');
        $lines = explode("\n", trim($response->streamedContent()));
        $this->assertCount(2, $lines);
        $record = array_combine(str_getcsv($lines[0], escape: ''), str_getcsv($lines[1], escape: ''));
        $this->assertSame("'=SYNTHETIC", $record['product_sku']);
        $this->assertSame('303', $record['net_sales']);
    }

    private function sell(User $owner, int $terminalId, array $items, int $amount, string $soldAt): void
    {
        app(CreateSaleAction::class)->execute([
            'terminal_id' => $terminalId,
            'sold_at' => $soldAt,
            'items' => $items,
            'payments' => [['payment_method' => 'CASH', 'amount' => $amount]],
        ], $owner->id);
    }
}
