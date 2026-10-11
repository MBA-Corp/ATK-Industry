<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleCancellationApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(
        string $permissionCode = 'sale.cancel'
    ): User {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => 'Cancel Sale',
            'code' => $permissionCode,
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    private function terminal(): Terminal
    {
        return Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS Cancellation Test',
            'is_active' => true,
        ]);
    }

    private function completedSale(
        User $cashier,
        Terminal $terminal,
        float $conversion = 1,
        float $quantity = 2
    ): array {
        $unit = Unit::create([
            'code' => $conversion === 1.0
                ? 'PCS'
                : 'PACK',

            'name' => $conversion === 1.0
                ? 'Pieces'
                : 'Pack',

            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'CANCEL-001',
            'name' => 'Cancellation Test Product',
            'is_active' => true,
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => $conversion,
            'selling_price' => 10000,
            'is_base' => $conversion === 1.0,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_number' => 'SALE-CANCEL-001',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $cashier->id,
            'status' => 'COMPLETED',
            'subtotal' => 20000,
            'grand_total' => 20000,
            'sold_at' => now(),
            'completed_at' => now(),
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'unit_code' => $unit->code,
            'quantity' => $quantity,
            'conversion_to_base' => $conversion,
            'catalog_unit_price' => 10000,
            'sale_unit_price' => 10000,
            'line_total' => 20000,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'payment_method' => 'CASH',
            'amount' => 20000,
            'paid_at' => now(),
        ]);

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 50,
        ]);

        return [
            $sale,
            $saleItem,
            $product,
            $productUnit,
        ];
    }

    public function test_completed_sale_can_be_cancelled_and_stock_is_restored(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $sale,
            $saleItem,
            $product,
        ] = $this->completedSale(
            $user,
            $terminal
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                [
                    'reason' => 'Wrong quantity input',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'CANCELLED'
            )
            ->assertJsonPath(
                'data.cancellation.reason',
                'Wrong quantity input'
            )
            ->assertJsonPath(
                'data.cancellation.cancelled_by.id',
                $user->id
            );

        $this->assertDatabaseHas(
            'sales',
            [
                'id' => $sale->id,
                'status' => 'CANCELLED',
            ]
        );

        $this->assertDatabaseHas(
            'sale_cancellations',
            [
                'sale_id' => $sale->id,
                'cancelled_by' => $user->id,
                'reason' => 'Wrong quantity input',
            ]
        );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 52,
            ]
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'product_id' => $product->id,
                'movement_type' => 'SALE_REVERSAL',
                'source_type' => 'SALE_CANCELLATION_ITEM',
                'source_id' => $saleItem->id,
                'quantity_delta_base' => 2,
                'quantity_before_base' => 50,
                'quantity_after_base' => 52,
                'actor_user_id' => $user->id,
                'terminal_id' => $terminal->id,
            ]
        );
    }

    public function test_cancellation_uses_historical_conversion_snapshot(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $sale,
            $saleItem,
            $product,
            $productUnit,
        ] = $this->completedSale(
            $user,
            $terminal,
            12,
            2
        );

        $productUnit->update([
            'conversion_to_base' => 24,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                [
                    'reason' => 'Cancel historical sale',
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 74,
            ]
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'source_type' => 'SALE_CANCELLATION_ITEM',
                'source_id' => $saleItem->id,
                'conversion_to_base' => 12,
                'quantity_delta_base' => 24,
            ]
        );
    }

    public function test_payment_and_sale_items_are_preserved_after_cancellation(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $sale,
        ] = $this->completedSale(
            $user,
            $terminal
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                [
                    'reason' => 'Customer cancelled',
                ]
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'sale_items',
            1
        );

        $this->assertDatabaseCount(
            'sale_payments',
            1
        );

        $this->assertDatabaseHas(
            'sale_payments',
            [
                'sale_id' => $sale->id,
                'payment_method' => 'CASH',
                'amount' => 20000,
            ]
        );
    }

    public function test_sale_cannot_be_cancelled_twice(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $sale,
        ] = $this->completedSale(
            $user,
            $terminal
        );

        $payload = [
            'reason' => 'Wrong transaction',
        ];

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                $payload
            )
            ->assertOk();

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                $payload
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'sale_cancellations',
            1
        );

        $this->assertDatabaseCount(
            'stock_movements',
            1
        );
    }

    public function test_draft_sale_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        $sale = Sale::create([
            'sale_number' => 'SALE-DRAFT-CANCEL',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'status' => 'DRAFT',
            'subtotal' => 0,
            'grand_total' => 0,
            'sold_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                [
                    'reason' => 'Should fail',
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'sale_cancellations',
            0
        );

        $this->assertDatabaseHas(
            'sales',
            [
                'id' => $sale->id,
                'status' => 'DRAFT',
            ]
        );
    }

    public function test_cancellation_reason_is_required(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $sale,
        ] = $this->completedSale(
            $user,
            $terminal
        );

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'reason'
            );

        $this->assertDatabaseCount(
            'sale_cancellations',
            0
        );
    }

    public function test_user_without_permission_cannot_cancel_sale(): void
    {
        $cashier = User::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $terminal = $this->terminal();

        [
            $sale,
        ] = $this->completedSale(
            $cashier,
            $terminal
        );

        $this
            ->actingAs($unauthorizedUser)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                [
                    'reason' => 'Forbidden cancellation',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'sales',
            [
                'id' => $sale->id,
                'status' => 'COMPLETED',
            ]
        );

        $this->assertDatabaseCount(
            'sale_cancellations',
            0
        );
    }

    public function test_guest_cannot_cancel_sale(): void
    {
        $cashier = User::factory()->create();
        $terminal = $this->terminal();

        [
            $sale,
        ] = $this->completedSale(
            $cashier,
            $terminal
        );

        $this
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                [
                    'reason' => 'Guest cancellation',
                ]
            )
            ->assertUnauthorized();

        $this->assertDatabaseCount(
            'sale_cancellations',
            0
        );
    }

    public function test_cancellation_preserves_fractional_stock_precision(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();
        [$sale, , $product] = $this->completedSale(
            $user,
            $terminal,
            0.5,
            0.000001
        );

        $this->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                ['reason' => 'Fractional stock reversal']
            )
            ->assertOk();

        $this->assertSame(
            '50.000001',
            InventoryBalance::query()
                ->where('product_id', $product->id)
                ->firstOrFail()
                ->quantity_base
        );

        $this->assertSame(
            '0.000001',
            StockMovement::query()
                ->where('source_type', 'SALE_CANCELLATION_ITEM')
                ->firstOrFail()
                ->quantity_delta_base
        );
    }

    public function test_cancellation_reverses_original_stock_movement_exactly(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();
        [$sale, $saleItem, $product] = $this->completedSale(
            $user,
            $terminal,
            0.5,
            0.5
        );

        StockMovement::create([
            'product_id' => $product->id,
            'product_unit_id' => $saleItem->product_unit_id,
            'movement_type' => 'SALE',
            'source_type' => 'SALE_ITEM',
            'source_id' => $saleItem->id,
            'source_quantity' => '0.500000',
            'conversion_to_base' => '0.500000',
            'quantity_delta_base' => '-0.250001',
            'quantity_before_base' => '50.250001',
            'quantity_after_base' => '50.000000',
            'actor_user_id' => $user->id,
            'terminal_id' => $terminal->id,
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(
                "/api/v1/sales/{$sale->id}/cancel",
                ['reason' => 'Reverse original movement']
            )
            ->assertOk();

        $this->assertSame(
            '50.250001',
            InventoryBalance::query()
                ->where('product_id', $product->id)
                ->firstOrFail()
                ->quantity_base
        );

        $this->assertSame(
            '0.250001',
            StockMovement::query()
                ->where('source_type', 'SALE_CANCELLATION_ITEM')
                ->firstOrFail()
                ->quantity_delta_base
        );
    }
}
