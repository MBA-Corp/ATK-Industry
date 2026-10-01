<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSupplierApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(
        string $permissionCode
    ): User {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => $permissionCode,
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

    private function product(
        string $sku = 'PS-001'
    ): Product {
        return Product::create([
            'sku' => $sku,
            'name' => 'Test Product',
        ]);
    }

    private function supplier(
        string $code = 'SUP-001'
    ): Supplier {
        return Supplier::create([
            'code' => $code,
            'name' => "Supplier {$code}",
        ]);
    }

    public function test_authenticated_user_can_list_product_suppliers(): void
    {
        $user = User::factory()->create();

        $product = $this->product();

        $supplierA = $this->supplier(
            'SUP-001'
        );

        $supplierB = $this->supplier(
            'SUP-002'
        );

        $product->productSuppliers()->create([
            'supplier_id' => $supplierA->id,
            'last_purchase_price' => 5000,
            'is_preferred' => false,
        ]);

        $product->productSuppliers()->create([
            'supplier_id' => $supplierB->id,
            'last_purchase_price' => 4500,
            'is_preferred' => true,
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/products/{$product->id}/suppliers"
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.supplier.code',
                'SUP-002'
            )
            ->assertJsonPath(
                'data.0.is_preferred',
                true
            );
    }

    public function test_user_with_permission_can_attach_supplier_to_product(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $product = $this->product();
        $supplier = $this->supplier();

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/products/{$product->id}/suppliers",
                [
                    'supplier_id' => $supplier->id,

                    'supplier_sku' => 'SUP-PEN-001',

                    'last_purchase_price' => 5000,

                    'is_preferred' => true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.supplier.id',
                $supplier->id
            )
            ->assertJsonPath(
                'data.supplier_sku',
                'SUP-PEN-001'
            )
            ->assertJsonPath(
                'data.last_purchase_price',
                5000
            )
            ->assertJsonPath(
                'data.is_preferred',
                true
            );

        $productSupplier = ProductSupplier::query()
            ->where(
                'product_id',
                $product->id
            )
            ->where(
                'supplier_id',
                $supplier->id
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'supplier_price_histories',
            [
                'product_supplier_id' => $productSupplier->id,

                'old_price' => null,
                'new_price' => 5000,
                'changed_by' => $user->id,
            ]
        );
    }

    public function test_user_without_permission_cannot_attach_supplier(): void
    {
        $user = User::factory()->create();

        $product = $this->product();
        $supplier = $this->supplier();

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/products/{$product->id}/suppliers",
                [
                    'supplier_id' => $supplier->id,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'product_suppliers',
            0
        );
    }

    public function test_inactive_supplier_cannot_be_attached_to_product(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $product = $this->product();

        $supplier = Supplier::create([
            'code' => 'SUP-INACTIVE',
            'name' => 'Inactive Supplier',
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/products/{$product->id}/suppliers",
                [
                    'supplier_id' => $supplier->id,

                    'last_purchase_price' => 5000,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'supplier_id'
            );
    }

    public function test_duplicate_product_supplier_returns_conflict(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $product = $this->product();
        $supplier = $this->supplier();

        $product->productSuppliers()->create([
            'supplier_id' => $supplier->id,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/products/{$product->id}/suppliers",
                [
                    'supplier_id' => $supplier->id,
                ]
            )
            ->assertConflict();

        $this->assertDatabaseCount(
            'product_suppliers',
            1
        );
    }

    public function test_setting_new_preferred_supplier_clears_previous_preferred(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $product = $this->product();

        $supplierA = $this->supplier(
            'SUP-001'
        );

        $supplierB = $this->supplier(
            'SUP-002'
        );

        $previous = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplierA->id,

                'is_preferred' => true,
            ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/products/{$product->id}/suppliers",
                [
                    'supplier_id' => $supplierB->id,

                    'is_preferred' => true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.is_preferred',
                true
            );

        $this->assertDatabaseHas(
            'product_suppliers',
            [
                'id' => $previous->id,
                'is_preferred' => false,
            ]
        );

        $this->assertSame(
            1,
            ProductSupplier::query()
                ->where(
                    'product_id',
                    $product->id
                )
                ->where(
                    'is_preferred',
                    true
                )
                ->count()
        );
    }

    public function test_purchase_price_change_creates_history(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $product = $this->product();
        $supplier = $this->supplier();

        $productSupplier = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,

                'last_purchase_price' => 5000,
            ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}/suppliers/{$productSupplier->id}",
                [
                    'last_purchase_price' => 6000,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.last_purchase_price',
                6000
            );

        $this->assertDatabaseHas(
            'supplier_price_histories',
            [
                'product_supplier_id' => $productSupplier->id,

                'old_price' => 5000,
                'new_price' => 6000,
                'changed_by' => $user->id,
            ]
        );
    }

    public function test_unchanged_purchase_price_does_not_create_history(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $product = $this->product();
        $supplier = $this->supplier();

        $productSupplier = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,

                'last_purchase_price' => 5000,
            ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}/suppliers/{$productSupplier->id}",
                [
                    'last_purchase_price' => 5000,
                ]
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'supplier_price_histories',
            0
        );
    }

    public function test_deactivating_preferred_relation_also_clears_preferred(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $product = $this->product();
        $supplier = $this->supplier();

        $productSupplier = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,

                'is_preferred' => true,
                'is_active' => true,
            ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}/suppliers/{$productSupplier->id}",
                [
                    'is_active' => false,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                false
            )
            ->assertJsonPath(
                'data.is_preferred',
                false
            );
    }

    public function test_relation_cannot_be_reactivated_when_supplier_is_inactive(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $product = $this->product();
        $supplier = $this->supplier();

        $productSupplier = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,

                'is_active' => false,
            ]);

        $supplier->update([
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$product->id}/suppliers/{$productSupplier->id}",
                [
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'is_active'
            );
    }

    public function test_product_supplier_from_another_product_cannot_be_updated(): void
    {
        $user = $this->userWithPermission(
            'product.update'
        );

        $productA = $this->product(
            'PS-001'
        );

        $productB = $this->product(
            'PS-002'
        );

        $supplier = $this->supplier();

        $productSupplier = $productB
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,
            ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/products/{$productA->id}/suppliers/{$productSupplier->id}",
                [
                    'supplier_sku' => 'SHOULD-NOT-WORK',
                ]
            )
            ->assertNotFound();
    }

    public function test_guest_cannot_access_product_supplier_endpoints(): void
    {
        $product = $this->product();
        $supplier = $this->supplier();

        $productSupplier = $product
            ->productSuppliers()
            ->create([
                'supplier_id' => $supplier->id,
            ]);

        $this
            ->getJson(
                "/api/v1/products/{$product->id}/suppliers"
            )
            ->assertUnauthorized();

        $this
            ->postJson(
                "/api/v1/products/{$product->id}/suppliers",
                [
                    'supplier_id' => $supplier->id,
                ]
            )
            ->assertUnauthorized();

        $this
            ->patchJson(
                "/api/v1/products/{$product->id}/suppliers/{$productSupplier->id}",
                [
                    'supplier_sku' => 'TEST',
                ]
            )
            ->assertUnauthorized();
    }
}
