<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(): array
    {
        $user = User::factory()->create();

        $terminal = Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS Receipt',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'RECEIPT-001',
            'name' => 'Original Product Name',
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
            'sale_number' => 'SALE-RECEIPT-001',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'status' => 'COMPLETED',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'sold_at' => now(),
            'completed_at' => now(),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,

            'product_sku' => 'RECEIPT-001',

            'product_name' => 'Original Product Name',

            'unit_code' => 'PCS',

            'quantity' => 2,

            'conversion_to_base' => 1,

            'catalog_unit_price' => 5000,

            'sale_unit_price' => 5000,

            'line_total' => 10000,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,

            'payment_method' => 'CASH',

            'amount' => 10000,

            'paid_at' => now(),
        ]);

        return [
            $user,
            $sale,
            $product,
            $productUnit,
        ];
    }

    public function test_authenticated_user_can_get_sale_receipt(): void
    {
        [
            $user,
            $sale,
        ] = $this->createSale();

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/sales/{$sale->id}/receipt"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.receipt_number',
                'SALE-RECEIPT-001'
            )
            ->assertJsonPath(
                'data.status',
                'COMPLETED'
            )
            ->assertJsonPath(
                'data.items.0.product_name',
                'Original Product Name'
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                '2.000000'
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                5000
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                10000
            )
            ->assertJsonPath(
                'data.payments.0.payment_method',
                'CASH'
            )
            ->assertJsonPath(
                'data.payments.0.amount',
                10000
            );
    }

    public function test_receipt_uses_historical_item_snapshots(): void
    {
        [
            $user,
            $sale,
            $product,
            $productUnit,
        ] = $this->createSale();

        $product->update([
            'sku' => 'CHANGED-SKU',
            'name' => 'Changed Product Name',
        ]);

        $productUnit->update([
            'selling_price' => 99999,
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/sales/{$sale->id}/receipt"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.product_sku',
                'RECEIPT-001'
            )
            ->assertJsonPath(
                'data.items.0.product_name',
                'Original Product Name'
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                5000
            );
    }

    public function test_receipt_can_be_requested_repeatedly_without_changing_sale(): void
    {
        [
            $user,
            $sale,
        ] = $this->createSale();

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/sales/{$sale->id}/receipt"
            )
            ->assertOk();

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/sales/{$sale->id}/receipt"
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'sales',
            1
        );

        $this->assertDatabaseCount(
            'sale_items',
            1
        );

        $this->assertDatabaseCount(
            'sale_payments',
            1
        );
    }

    public function test_missing_receipt_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/sales/999999/receipt'
            )
            ->assertNotFound();
    }

    public function test_guest_cannot_get_receipt(): void
    {
        [
            ,
            $sale,
        ] = $this->createSale();

        $this
            ->getJson(
                "/api/v1/sales/{$sale->id}/receipt"
            )
            ->assertUnauthorized();
    }
}
