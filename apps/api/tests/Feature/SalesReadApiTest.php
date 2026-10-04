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

class SalesReadApiTest extends TestCase
{
    use RefreshDatabase;

    private function terminal(
        string $name = 'POS Read'
    ): Terminal {
        return Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function sale(
        User $cashier,
        Terminal $terminal,
        string $number,
        mixed $soldAt = null,
        string $status = 'COMPLETED'
    ): Sale {
        return Sale::create([
            'sale_number' => $number,
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $cashier->id,
            'status' => $status,
            'subtotal' => 10000,
            'grand_total' => 10000,
            'sold_at' => $soldAt ?? now(),
            'completed_at' => $status === 'COMPLETED'
                ? ($soldAt ?? now())
                : null,
        ]);
    }

    public function test_authenticated_user_can_list_sales(): void
    {
        $user = User::factory()->create();
        $terminal = $this->terminal();

        $this->sale(
            $user,
            $terminal,
            'SALE-READ-001'
        );

        $this->sale(
            $user,
            $terminal,
            'SALE-READ-002'
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/sales');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'meta.total',
                2
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'data.0.cashier.id',
                $user->id
            )
            ->assertJsonPath(
                'data.0.terminal.id',
                $terminal->id
            );
    }

    public function test_sales_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        $terminal = $this->terminal();

        $this->sale(
            $user,
            $terminal,
            'SALE-COMPLETED',
            now(),
            'COMPLETED'
        );

        $this->sale(
            $user,
            $terminal,
            'SALE-DRAFT',
            now(),
            'DRAFT'
        );

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/sales?status=COMPLETED'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.sale_number',
                'SALE-COMPLETED'
            );
    }

    public function test_sales_can_be_filtered_by_terminal_and_cashier(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $terminalA = $this->terminal(
            'POS A'
        );

        $terminalB = $this->terminal(
            'POS B'
        );

        $this->sale(
            $userA,
            $terminalA,
            'SALE-A'
        );

        $this->sale(
            $userB,
            $terminalB,
            'SALE-B'
        );

        $this
            ->actingAs($userA)
            ->getJson(
                '/api/v1/sales'
                .'?terminal_id='
                .$terminalA->id
                .'&cashier_user_id='
                .$userA->id
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.sale_number',
                'SALE-A'
            );
    }

    public function test_sales_can_be_filtered_by_date_range(): void
    {
        $user = User::factory()->create();
        $terminal = $this->terminal();

        $this->sale(
            $user,
            $terminal,
            'SALE-OLD',
            now()->subDays(10)
        );

        $this->sale(
            $user,
            $terminal,
            'SALE-TODAY',
            now()
        );

        $date = now()->toDateString();

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/sales?date_from={$date}&date_to={$date}"
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.sale_number',
                'SALE-TODAY'
            );
    }

    public function test_sales_can_be_searched_by_sale_number(): void
    {
        $user = User::factory()->create();
        $terminal = $this->terminal();

        $this->sale(
            $user,
            $terminal,
            'SALE-20261004-ABC123'
        );

        $this->sale(
            $user,
            $terminal,
            'SALE-20261004-XYZ999'
        );

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/sales?search=ABC123'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.sale_number',
                'SALE-20261004-ABC123'
            );
    }

    public function test_sales_list_is_paginated(): void
    {
        $user = User::factory()->create();
        $terminal = $this->terminal();

        $this->sale(
            $user,
            $terminal,
            'SALE-PAGE-001',
            now()->subMinutes(2)
        );

        $this->sale(
            $user,
            $terminal,
            'SALE-PAGE-002',
            now()->subMinute()
        );

        $this->sale(
            $user,
            $terminal,
            'SALE-PAGE-003',
            now()
        );

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/sales?per_page=2'
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'meta.per_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                3
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            );
    }

    public function test_authenticated_user_can_view_sale_detail_with_items_and_payments(): void
    {
        $user = User::factory()->create();
        $terminal = $this->terminal();

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'READ-001',
            'name' => 'Read Test Product',
            'is_active' => true,
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 10000,
            'is_base' => true,
            'is_active' => true,
        ]);

        $sale = $this->sale(
            $user,
            $terminal,
            'SALE-DETAIL-001'
        );

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'unit_code' => $unit->code,
            'quantity' => 1,
            'conversion_to_base' => 1,
            'catalog_unit_price' => 10000,
            'sale_unit_price' => 10000,
            'line_total' => 10000,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'payment_method' => 'CASH',
            'amount' => 10000,
            'paid_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/sales/{$sale->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.sale_number',
                'SALE-DETAIL-001'
            )
            ->assertJsonPath(
                'data.items.0.product_sku',
                'READ-001'
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                '1.000000'
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

    public function test_missing_sale_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/v1/sales/999999')
            ->assertNotFound();
    }

    public function test_guest_cannot_list_sales(): void
    {
        $this
            ->getJson('/api/v1/sales')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_view_sale_detail(): void
    {
        $this
            ->getJson('/api/v1/sales/1')
            ->assertUnauthorized();
    }
}
