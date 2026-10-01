<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $code): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => $code,
            'code' => $code,
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    public function test_authenticated_user_can_list_suppliers(): void
    {
        $user = User::factory()->create();

        Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier A',
        ]);

        Supplier::create([
            'code' => 'SUP-002',
            'name' => 'Supplier B',
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/v1/suppliers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'meta.total',
                2
            );
    }

    public function test_suppliers_can_be_searched_and_filtered(): void
    {
        $user = User::factory()->create();

        Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Sumber Makmur',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);

        Supplier::create([
            'code' => 'SUP-002',
            'name' => 'Mitra Lama',
            'city' => 'Bogor',
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/suppliers?search=Sumber&is_active=1'
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.code',
                'SUP-001'
            );
    }

    public function test_authenticated_user_can_get_supplier_detail(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier Detail',
            'address' => 'Jl. Contoh No. 1',
            'city' => 'Jakarta',
            'phone' => '021123456',
        ]);

        $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/suppliers/{$supplier->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $supplier->id
            )
            ->assertJsonPath(
                'data.code',
                'SUP-001'
            )
            ->assertJsonPath(
                'data.city',
                'Jakarta'
            );
    }

    public function test_user_with_permission_can_create_supplier(): void
    {
        $user = $this->userWithPermission(
            'supplier.create'
        );

        $this
            ->actingAs($user)
            ->postJson('/api/v1/suppliers', [
                'code' => ' sup-003 ',
                'name' => 'Supplier Baru',
                'address' => 'Jl. Supplier',
                'city' => 'Bandung',
                'phone' => '08123456789',
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'SUP-003'
            )
            ->assertJsonPath(
                'data.name',
                'Supplier Baru'
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );

        $this->assertDatabaseHas(
            'suppliers',
            [
                'code' => 'SUP-003',
                'name' => 'Supplier Baru',
                'city' => 'Bandung',
                'is_active' => true,
            ]
        );
    }

    public function test_user_without_permission_cannot_create_supplier(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/suppliers', [
                'code' => 'SUP-004',
                'name' => 'Supplier',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'suppliers',
            [
                'code' => 'SUP-004',
            ]
        );
    }

    public function test_duplicate_supplier_code_is_rejected(): void
    {
        $user = $this->userWithPermission(
            'supplier.create'
        );

        Supplier::create([
            'code' => 'SUP-005',
            'name' => 'Supplier Lama',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/suppliers', [
                'code' => 'sup-005',
                'name' => 'Supplier Duplikat',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'code'
            );
    }

    public function test_user_with_permission_can_update_supplier(): void
    {
        $user = $this->userWithPermission(
            'supplier.update'
        );

        $supplier = Supplier::create([
            'code' => 'SUP-006',
            'name' => 'Nama Lama',
            'city' => 'Jakarta',
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/suppliers/{$supplier->id}",
                [
                    'code' => ' sup-new ',
                    'name' => 'Nama Baru',
                    'city' => 'Depok',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.code',
                'SUP-NEW'
            )
            ->assertJsonPath(
                'data.name',
                'Nama Baru'
            );

        $this->assertDatabaseHas(
            'suppliers',
            [
                'id' => $supplier->id,
                'code' => 'SUP-NEW',
                'name' => 'Nama Baru',
                'city' => 'Depok',
            ]
        );
    }

    public function test_supplier_can_be_deactivated(): void
    {
        $user = $this->userWithPermission(
            'supplier.update'
        );

        $supplier = Supplier::create([
            'code' => 'SUP-007',
            'name' => 'Supplier',
        ]);

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/suppliers/{$supplier->id}",
                [
                    'is_active' => false,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas(
            'suppliers',
            [
                'id' => $supplier->id,
                'is_active' => false,
            ]
        );
    }

    public function test_guest_cannot_access_supplier_endpoints(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-008',
            'name' => 'Supplier',
        ]);

        $this
            ->getJson('/api/v1/suppliers')
            ->assertUnauthorized();

        $this
            ->getJson(
                "/api/v1/suppliers/{$supplier->id}"
            )
            ->assertUnauthorized();

        $this
            ->postJson('/api/v1/suppliers', [
                'code' => 'SUP-009',
                'name' => 'Supplier Baru',
            ])
            ->assertUnauthorized();

        $this
            ->patchJson(
                "/api/v1/suppliers/{$supplier->id}",
                [
                    'name' => 'Diubah',
                ]
            )
            ->assertUnauthorized();
    }
}
