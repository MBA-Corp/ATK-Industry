<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_belongs_to_category(): void
    {
        $category = Category::create([
            'name' => 'Alat Tulis',
        ]);

        $product = Product::create([
            'sku' => 'ATK-001',
            'name' => 'Pulpen',
            'category_id' => $category->id,
        ]);

        $this->assertTrue(
            $product->category->is($category)
        );
    }

    public function test_product_has_multiple_units(): void
    {
        $product = Product::create([
            'sku' => 'ATK-002',
            'name' => 'Pensil',
        ]);

        $pcs = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $pack = Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'conversion_to_base' => 1,
            'selling_price' => 5000,
            'is_base' => true,
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $pack->id,
            'conversion_to_base' => 12,
            'selling_price' => 55000,
            'is_base' => false,
        ]);

        $this->assertCount(
            2,
            $product->fresh()->productUnits
        );
    }

    public function test_product_unit_belongs_to_product_and_unit(): void
    {
        $product = Product::create([
            'sku' => 'ATK-003',
            'name' => 'Spidol',
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 10000,
            'is_base' => true,
        ]);

        $this->assertTrue(
            $productUnit->product->is($product)
        );

        $this->assertTrue(
            $productUnit->unit->is($unit)
        );
    }

    public function test_product_has_barcodes_and_barcode_can_reference_unit(): void
    {
        $product = Product::create([
            'sku' => 'ATK-004',
            'name' => 'Buku Tulis',
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 8000,
            'is_base' => true,
        ]);

        $barcode = ProductBarcode::create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'barcode' => '8991234567890',
            'is_primary' => true,
        ]);

        $this->assertTrue(
            $barcode->product->is($product)
        );

        $this->assertTrue(
            $barcode->productUnit->is($productUnit)
        );

        $this->assertCount(
            1,
            $product->fresh()->barcodes
        );

        $this->assertCount(
            1,
            $productUnit->fresh()->barcodes
        );
    }

    public function test_catalog_model_casts_and_defaults_work(): void
    {
        $product = Product::create([
            'sku' => 'ATK-005',
            'name' => 'Penghapus',
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 12,
            'selling_price' => 25000,
        ]);

        $this->assertTrue($product->is_active);
        $this->assertTrue($unit->is_active);

        $this->assertFalse(
            $productUnit->is_base
        );

        $this->assertTrue(
            $productUnit->is_active
        );

        $this->assertSame(
            '12.000000',
            $productUnit->conversion_to_base
        );

        $this->assertSame(
            25000,
            $productUnit->selling_price
        );
    }
}
