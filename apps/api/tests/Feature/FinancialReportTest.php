<?php

namespace Tests\Feature;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RetailFixture;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase, RetailFixture;

    private const RANGE = '?date_from=2026-11-26&date_to=2026-11-26';

    public function test_historical_cost_return_and_expense_feed_perpetual_profit_once(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0));
        [$owner, $product, $unit, $sale] = $this->retailFixture('3', 200);
        $this->actingAs($owner);
        $unit->update(['selling_price' => 10000]);
        $this->postJson("/api/v1/sales/{$sale->id}/returns", [
            'idempotency_key' => (string) Str::uuid(), 'reason' => 'Synthetic return',
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => '0.5', 'condition' => 'resellable']],
        ])->assertOk();
        $this->postJson('/api/v1/expenses', $this->expense())->assertOk();

        $api = '/api/v1/reports/';
        $this->getJson($api.'profit-loss'.self::RANGE)->assertOk()
            ->assertJsonPath('data.net_sales', '500')
            ->assertJsonPath('data.perpetual_hpp', '250.000000')
            ->assertJsonPath('data.hpp', '250.000000')
            ->assertJsonPath('data.gross_profit', '250.000000')
            ->assertJsonPath('data.simple_net_profit', '200.000000')
            ->assertJsonPath('data.margin_percentage', '50.000000')
            ->assertJsonPath('data.closing_inventory', '750.000000')
            ->assertJsonPath('data.provisional', true);
        $this->getJson($api.'margins/products'.self::RANGE.'&product_id='.$product->id)->assertOk()
            ->assertJsonPath('data.0.product_sku', 'SYNTHETIC-RETAIL')
            ->assertJsonPath('data.0.hpp', '250.000000');
        $this->getJson($api.'margins/transactions'.self::RANGE)->assertOk()
            ->assertJsonPath('data.0.sale_id', $sale->id)
            ->assertJsonPath('data.0.gross_profit', '250.000000');
        $this->get($api.'profit-loss/export'.self::RANGE)->assertOk()->assertDownload('profit-loss.csv');
    }

    public function test_missing_cost_and_late_interleaved_records_leave_unavailable_values_null(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0));
        [$owner, $product, , $sale] = $this->retailFixture();
        DB::table('sale_items')->where('sale_id', $sale->id)->update(['cost_total' => null]);
        $movements = app(ApplyStockMovementAction::class);
        $movements->execute($product->id, 'OPENING', 'SYNTHETIC', 2, '1', occurredAt: now()->addDays(2), incomingValue: '50');
        $movements->execute($product->id, 'OPENING', 'SYNTHETIC', 3, '1', occurredAt: now(), incomingValue: '80');

        $this->actingAs($owner)->getJson('/api/v1/reports/profit-loss'.self::RANGE)->assertOk()
            ->assertJsonPath('data.net_sales', '303')
            ->assertJsonPath('data.hpp', null)
            ->assertJsonPath('data.closing_inventory', null)
            ->assertJsonPath('data.simple_net_profit', null)
            ->assertJsonPath('data.missing_cost_lines', 1)
            ->assertJsonPath('data.reconciliation_status', 'incomplete');
    }

    public function test_expense_creation_idempotency_revision_conflict_and_void_are_auditable(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0));
        [$owner] = $this->retailFixture();
        $payload = $this->expense();
        $this->actingAs($owner);
        $id = $this->postJson('/api/v1/expenses', $payload)->assertOk()->json('data.id');
        $this->postJson('/api/v1/expenses', $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/expenses', array_replace($payload, ['amount' => 55]))->assertConflict();
        $path = "/api/v1/expenses/$id";
        $this->patchJson($path, ['version' => 1, 'amount' => 70])->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson($path, ['version' => 1, 'amount' => 80])->assertConflict();
        $this->patchJson($path, ['version' => 2, 'void' => true])->assertOk()->assertJsonPath('data.version', 3);
        $this->patchJson($path, ['version' => 3, 'amount' => 10])->assertUnprocessable();
        $this->getJson($path.'/history')->assertOk()->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.amount', '50')->assertJsonPath('data.1.amount', '70')
            ->assertJsonPath('data.2.actor_id', $owner->id);
        $this->getJson('/api/v1/reports/profit-loss'.self::RANGE)->assertOk()
            ->assertJsonPath('data.operating_expenses', '0');
    }

    public function test_financial_and_expense_permissions_are_checked_independently(): void
    {
        $this->getJson('/api/v1/reports/profit-loss'.self::RANGE)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/reports/margins/products'.self::RANGE)->assertForbidden();
        $this->getJson('/api/v1/reports/profit-loss/export'.self::RANGE)->assertForbidden();
        $this->postJson('/api/v1/expenses', $this->expense())->assertForbidden();
    }

    private function expense(): array
    {
        return [
            'request_id' => (string) Str::uuid(), 'expense_date' => '2026-11-26',
            'category' => 'Utilities', 'description' => 'Synthetic expense', 'amount' => 50,
        ];
    }
}
