<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_products(): void
    {
        $user = User::factory()->create();

        $category = Category::create([
            'name' => 'Alat Tulis',
        ]);

        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
        ]);

        $product = Product::create([
            'sku' => 'ATK-001',
            'name' => 'Pulpen Biru',
            'category_id' => $category->id,
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 5000,
            'is_base' => true,
        ]);

        ProductBarcode::create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'barcode' => '8990000000001',
            'is_primary' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/products');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'ATK-001')
            ->assertJsonPath('data.0.name', 'Pulpen Biru')
            ->assertJsonPath('data.0.category.name', 'Alat Tulis')
            ->assertJsonPath('data.0.units.0.unit_code', 'PCS')
            ->assertJsonPath('data.0.units.0.selling_price', 5000)
            ->assertJsonPath(
                'data.0.barcodes.0.barcode',
                '8990000000001'
            )
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_authenticated_user_can_get_product_detail(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'sku' => 'ATK-002',
            'name' => 'Pensil 2B',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', 'ATK-002')
            ->assertJsonPath('data.name', 'Pensil 2B')
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_product_search_works_by_sku(): void
    {
        $user = User::factory()->create();

        Product::create([
            'sku' => 'SPECIAL-001',
            'name' => 'Pulpen',
        ]);

        Product::create([
            'sku' => 'ATK-999',
            'name' => 'Penghapus',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/products?search=SPECIAL');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'SPECIAL-001');
    }

    public function test_product_search_works_by_name(): void
    {
        $user = User::factory()->create();

        Product::create([
            'sku' => 'ATK-003',
            'name' => 'Buku Gambar A4',
        ]);

        Product::create([
            'sku' => 'ATK-004',
            'name' => 'Spidol Hitam',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/products?search=Gambar');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'ATK-003');
    }

    public function test_product_search_works_by_barcode(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'sku' => 'ATK-005',
            'name' => 'Penggaris',
        ]);

        ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => '8995555555555',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/products?search=8995555555555');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);
    }

    public function test_products_can_be_filtered_by_category_and_active_status(): void
    {
        $user = User::factory()->create();

        $stationery = Category::create([
            'name' => 'Stationery',
        ]);

        $books = Category::create([
            'name' => 'Books',
        ]);

        Product::create([
            'sku' => 'ATK-006',
            'name' => 'Pulpen Aktif',
            'category_id' => $stationery->id,
            'is_active' => true,
        ]);

        Product::create([
            'sku' => 'ATK-007',
            'name' => 'Pulpen Nonaktif',
            'category_id' => $stationery->id,
            'is_active' => false,
        ]);

        Product::create([
            'sku' => 'ATK-008',
            'name' => 'Buku',
            'category_id' => $books->id,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/products?category_id={$stationery->id}&is_active=true"
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'ATK-006');
    }

    public function test_products_can_be_sorted_by_name_descending(): void
    {
        $user = User::factory()->create();

        Product::create([
            'sku' => 'ATK-009',
            'name' => 'Buku',
        ]);

        Product::create([
            'sku' => 'ATK-010',
            'name' => 'Pulpen',
        ]);

        Product::create([
            'sku' => 'ATK-011',
            'name' => 'Spidol',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/products?sort=-name');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Spidol')
            ->assertJsonPath('data.1.name', 'Pulpen')
            ->assertJsonPath('data.2.name', 'Buku');
    }

    public function test_guest_cannot_access_product_endpoints(): void
    {
        $product = Product::create([
            'sku' => 'ATK-012',
            'name' => 'Kertas',
        ]);

        $this
            ->getJson('/api/v1/products')
            ->assertUnauthorized();

        $this
            ->getJson("/api/v1/products/{$product->id}")
            ->assertUnauthorized();
    }

    public function test_missing_product_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/v1/products/999999')
            ->assertNotFound();
    }
}
