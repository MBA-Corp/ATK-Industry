<?php

namespace Tests\Feature;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Actions\Sales\ReturnSaleAction;
use App\Models\User;
use App\Support\CostMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RetailFixture;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use RefreshDatabase, RetailFixture;

    private function payload(int $itemId, string $quantity = '1', string $condition = 'resellable'): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'reason' => 'Synthetic return',
            'items' => [['sale_item_id' => $itemId, 'quantity' => $quantity, 'condition' => $condition]]];
    }

    public function test_partial_return_uses_original_cost_after_new_receipt_and_is_idempotent(): void
    {
        [$user, $product, $pu, $sale] = $this->retailFixture();
        $item = $sale->items->first();
        $this->assertSame('300.000000', CostMath::round((string) $item->cost_total));
        app(ApplyStockMovementAction::class)->execute($product->id, 'PURCHASE_RECEIPT', 'SYNTHETIC', 2, '10', incomingValue: '2000');
        $payload = $this->payload($item->id);
        $this->actingAs($user);
        $response = $this->postJson("/api/v1/sales/{$sale->id}/returns", $payload)
            ->assertOk()->assertJsonPath('data.refund_status', 'AWAITING_MANUAL_RECORD');
        $this->postJson("/api/v1/sales/{$sale->id}/returns", $payload)
            ->assertOk()->assertJsonPath('data.id', $response->json('data.id'));
        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertDatabaseCount('sale_return_items', 1);
        $this->assertSame('2800.000000', CostMath::round((string) DB::table('inventory_costs')->value('inventory_value')));
        $this->assertSame('100.000000', CostMath::round((string) DB::table('sale_return_items')->value('cost_reversal')));
        $this->postJson("/api/v1/sales/{$sale->id}/cancel", ['reason' => 'Attempt double reversal'])->assertUnprocessable();
    }

    public function test_damaged_return_does_not_restock_and_overreturn_rolls_back(): void
    {
        [$user, $product, $pu, $sale] = $this->retailFixture();
        $this->actingAs($user);
        $this->postJson("/api/v1/sales/{$sale->id}/returns", $this->payload($sale->items->first()->id, '2', 'damaged'))->assertOk();
        $this->assertSame('7.000000', CostMath::round((string) DB::table('inventory_balances')->value('quantity_base')));
        $this->assertSame('700.000000', CostMath::round((string) DB::table('inventory_costs')->value('inventory_value')));
        $this->postJson("/api/v1/sales/{$sale->id}/returns", $this->payload($sale->items->first()->id, '2'))->assertUnprocessable();
        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertDatabaseCount('sale_return_items', 1);
    }

    public function test_fractional_refund_allocation_cannot_exceed_original_line_total(): void
    {
        [$user, $product, $pu, $sale] = $this->retailFixture('1', 1);
        $item = $sale->items->first();
        $action = app(ReturnSaleAction::class);
        $action->execute($sale->id, $this->payload($item->id, '0.5'), $user->id);
        $action->execute($sale->id, $this->payload($item->id, '0.5'), $user->id);
        $this->assertSame(1, (int) DB::table('sale_return_items')->sum('refund_amount'));
        $this->assertSame('10.000000', CostMath::round((string) DB::table('inventory_balances')->value('quantity_base')));
        $this->assertSame('1000.000000', CostMath::round((string) DB::table('inventory_costs')->value('inventory_value')));
    }

    public function test_manual_refund_requires_exact_amount_and_cannot_be_recorded_twice(): void
    {
        [$user, $product, $pu, $sale] = $this->retailFixture();
        $id = app(ReturnSaleAction::class)->execute($sale->id, $this->payload($sale->items->first()->id), $user->id);
        $this->actingAs($user);
        $refund = ['idempotency_key' => (string) Str::uuid(), 'amount' => 101,
            'method' => 'CASH', 'reference' => 'Synthetic operator receipt', 'confirm_record_only' => true];
        $this->postJson("/api/v1/sale-returns/{$id}/refund", array_merge($refund, ['amount' => 102]))->assertUnprocessable();
        $this->postJson("/api/v1/sale-returns/{$id}/refund", $refund)->assertOk()
            ->assertJsonPath('data.refund.status', 'MANUALLY_RECORDED');
        $this->postJson("/api/v1/sale-returns/{$id}/refund", $refund)->assertOk();
        $refund['idempotency_key'] = (string) Str::uuid();
        $this->postJson("/api/v1/sale-returns/{$id}/refund", $refund)->assertConflict();
        $this->assertDatabaseCount('sale_refunds', 1);
    }

    public function test_return_and_refund_permissions_are_required(): void
    {
        $this->postJson('/api/v1/sales/1/returns')->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/v1/sales/1/returns')->assertForbidden();
        $this->getJson('/api/v1/sale-returns/1')->assertForbidden();
        $this->postJson('/api/v1/sale-returns/1/refund')->assertForbidden();
    }
}
