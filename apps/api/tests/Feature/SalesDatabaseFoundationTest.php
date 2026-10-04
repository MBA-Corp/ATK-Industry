<?php

namespace Tests\Feature;

use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_tables_exist(): void
    {
        $this->assertTrue(
            Schema::hasTable('sales')
        );

        $this->assertTrue(
            Schema::hasTable('sale_items')
        );

        $this->assertTrue(
            Schema::hasTable('sale_payments')
        );
    }

    public function test_sales_tables_have_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'sales',
                [
                    'id',
                    'sale_number',
                    'client_reference',
                    'terminal_id',
                    'cashier_user_id',
                    'status',
                    'subtotal',
                    'grand_total',
                    'sold_at',
                    'completed_at',
                    'created_at',
                    'updated_at',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'sale_items',
                [
                    'id',
                    'sale_id',
                    'product_id',
                    'product_unit_id',
                    'product_sku',
                    'product_name',
                    'unit_code',
                    'quantity',
                    'conversion_to_base',
                    'catalog_unit_price',
                    'sale_unit_price',
                    'line_total',
                    'created_at',
                    'updated_at',
                ]
            )
        );
        $this->assertTrue(
            Schema::hasColumns(
                'sale_payments',
                [
                    'id',
                    'sale_id',
                    'payment_method',
                    'amount',
                    'reference',
                    'paid_at',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_sale_number_must_be_unique(): void
    {
        $user = User::factory()->create();

        $terminal = Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS Test',
            'is_active' => true,
        ]);

        DB::table('sales')->insert([
            'sale_number' => 'SALE-001',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'status' => 'DRAFT',
            'subtotal' => 0,
            'grand_total' => 0,
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('sales')->insert([
            'sale_number' => 'SALE-001',
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'status' => 'DRAFT',
            'subtotal' => 0,
            'grand_total' => 0,
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_client_reference_must_be_unique_when_present(): void
    {
        $user = User::factory()->create();

        $terminal = Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS Sync Test',
            'is_active' => true,
        ]);

        $clientReference = (string) Str::uuid();

        DB::table('sales')->insert([
            'sale_number' => 'SALE-002',
            'client_reference' => $clientReference,
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'status' => 'DRAFT',
            'subtotal' => 0,
            'grand_total' => 0,
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('sales')->insert([
            'sale_number' => 'SALE-003',
            'client_reference' => $clientReference,
            'terminal_id' => $terminal->id,
            'cashier_user_id' => $user->id,
            'status' => 'DRAFT',
            'subtotal' => 0,
            'grand_total' => 0,
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
