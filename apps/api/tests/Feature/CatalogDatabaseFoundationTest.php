<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_foundation_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('units'));
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('product_units'));
        $this->assertTrue(Schema::hasTable('product_barcodes'));
    }

    public function test_catalog_foundation_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('products', [
            'id',
            'sku',
            'name',
            'category_id',
            'size',
            'color',
            'variant_label',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('product_units', [
            'id',
            'product_id',
            'unit_id',
            'conversion_to_base',
            'selling_price',
            'is_base',
            'is_active',
        ]));

        $this->assertTrue(Schema::hasColumns('product_barcodes', [
            'id',
            'product_id',
            'product_unit_id',
            'barcode',
            'is_primary',
        ]));
    }

    public function test_product_sku_must_be_unique(): void
    {
        DB::table('products')->insert([
            'sku' => 'ATK-001',
            'name' => 'Test Product',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('products')->insert([
            'sku' => 'ATK-001',
            'name' => 'Duplicate Product',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_product_unit_must_be_unique_per_product_and_unit(): void
    {
        $unitId = DB::table('units')->insertGetId([
            'code' => 'PCS',
            'name' => 'Pieces',
            'is_active' => true,
        ]);

        $productId = DB::table('products')->insertGetId([
            'sku' => 'ATK-002',
            'name' => 'Product Unit Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_units')->insert([
            'product_id' => $productId,
            'unit_id' => $unitId,
            'conversion_to_base' => 1,
            'selling_price' => 5000,
            'is_base' => true,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        DB::table('product_units')->insert([
            'product_id' => $productId,
            'unit_id' => $unitId,
            'conversion_to_base' => 1,
            'selling_price' => 5500,
            'is_base' => false,
            'is_active' => true,
        ]);
    }

    public function test_product_barcode_must_be_unique(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'ATK-003',
            'name' => 'Barcode Test Product',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_barcodes')->insert([
            'product_id' => $productId,
            'barcode' => '8991234567890',
            'is_primary' => true,
        ]);

        $this->expectException(QueryException::class);

        DB::table('product_barcodes')->insert([
            'product_id' => $productId,
            'barcode' => '8991234567890',
            'is_primary' => false,
        ]);
    }
}
