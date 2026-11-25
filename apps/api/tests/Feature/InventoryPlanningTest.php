<?php

namespace Tests\Feature;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Actions\Sales\CreateSaleAction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\RetailFixture;
use Tests\TestCase;

class InventoryPlanningTest extends TestCase
{
    use RefreshDatabase, RetailFixture;

    public function test_rop_uses_completed_days_zeros_original_units_and_population_deviation(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(17, 0, 0));
        [$owner, $product, $unit, $sale] = $this->retailFixture('2', 200);
        $this->travel(2)->days();
        $otherSale = app(CreateSaleAction::class)->execute([
            'terminal_id' => $sale->terminal_id,
            'items' => [['product_unit_id' => $unit->id, 'quantity' => '4']],
            'payments' => [['payment_method' => 'CASH', 'amount' => 800]],
        ], $owner->id);
        $this->actingAs($owner)->postJson("/api/v1/sales/{$otherSale->id}/returns", [
            'idempotency_key' => (string) Str::uuid(), 'reason' => 'Synthetic damage',
            'items' => [['sale_item_id' => $otherSale->items->first()->id, 'quantity' => '1', 'condition' => 'damaged']],
        ])->assertOk();
        $this->travel(1)->day();
        app(CreateSaleAction::class)->execute([
            'terminal_id' => $sale->terminal_id,
            'items' => [['product_unit_id' => $unit->id, 'quantity' => '1']],
            'payments' => [['payment_method' => 'CASH', 'amount' => 200]],
        ], $owner->id);
        $path = "/api/v1/inventory/{$product->id}/planning";
        $this->putJson($path, $this->policy())->assertOk();
        $this->getJson($path)->assertOk()->assertJsonPath('data.observed_days', 3)
            ->assertJsonPath('data.daily_demand.2026-11-23', '2.000000')
            ->assertJsonPath('data.daily_demand.2026-11-24', '0.000000')
            ->assertJsonPath('data.daily_demand.2026-11-25', '4.000000')
            ->assertJsonMissingPath('data.daily_demand.2026-11-26')
            ->assertJsonPath('data.average_daily_demand_base', '2.000000')
            ->assertJsonPath('data.standard_deviation_daily_demand_base', '1.632993')
            ->assertJsonPath('data.safety_stock_base', '6.000000')
            ->assertJsonPath('data.reorder_point_base', '14.000000')
            ->assertJsonPath('data.stock_status', 'critical');

        app(ApplyStockMovementAction::class)->execute($product->id, 'ADJUSTMENT', 'SYNTHETIC', 77, '5');
        $this->getJson('/api/v1/inventory/reorder-alerts?kind=overstock')->assertOk()
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.stock_base', '8.000000');
    }

    public function test_insufficient_history_falls_back_only_to_a_configured_minimum(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0, 0));
        [$owner, $product] = $this->retailFixture();
        $path = "/api/v1/inventory/{$product->id}/planning";
        $this->actingAs($owner)->getJson($path)->assertOk()
            ->assertJsonPath('data.method', 'insufficient_data')->assertJsonPath('data.reorder_point_base', null);
        $this->putJson($path, array_replace($this->policy(), [
            'history_from' => '2026-11-26', 'minimum_stock' => '8', 'maximum_stock' => '10',
            'quantity_step' => '0.000001',
        ]))->assertOk()->assertJsonPath('data.observed_days', 0)
            ->assertJsonPath('data.quantity_step_base', '0.000001')
            ->assertJsonPath('data.method', 'manual_minimum')
            ->assertJsonPath('data.reorder_point_base', '8.000000')
            ->assertJsonPath('data.stock_status', 'reorder_needed');
        $this->putJson($path, $this->policy())->assertUnprocessable()->assertJsonValidationErrors('history_from');
        $this->putJson($path, array_replace($this->policy(), [
            'history_from' => '2026-11-26', 'minimum_stock' => '8', 'maximum_stock' => '7',
            'quantity_step' => '0',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['maximum_stock', 'quantity_step']);
    }

    public function test_alert_cursor_progresses_across_an_empty_page_and_permissions_are_required(): void
    {
        $this->getJson('/api/v1/inventory/reorder-alerts')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/inventory/reorder-alerts')->assertForbidden();
        $this->putJson('/api/v1/inventory/1/planning', $this->policy())->assertForbidden();
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(17, 0, 0));
        [$owner, $product] = $this->retailFixture();
        Product::create(['sku' => 'SYNTHETIC-NEXT', 'name' => 'Next product']);
        $this->actingAs($owner)->getJson('/api/v1/inventory/reorder-alerts?kind=overstock&per_page=1')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('next_after_product_id', $product->id);
        $this->getJson('/api/v1/inventory/reorder-alerts?kind=overstock&after_product_id='.$product->id)
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('next_after_product_id', null);
    }

    private function policy(): array
    {
        return [
            'history_from' => '2026-11-23', 'lead_time_days' => 4,
            'demand_window_days' => 3, 'minimum_history_days' => 3,
            'quantity_step' => '1', 'minimum_stock' => '1', 'maximum_stock' => '3',
        ];
    }
}
