<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Kasir',
                'code' => 'cashier',
            ],
            [
                'name' => 'Manager',
                'code' => 'manager',
            ],
            [
                'name' => 'Supervisor',
                'code' => 'supervisor',
            ],
            [
                'name' => 'Owner',
                'code' => 'owner',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                [
                    'code' => $role['code'],
                ],
                [
                    'name' => $role['name'],
                ]
            );
        }

        $permissions = [
            [
                'name' => 'Create Category',
                'code' => 'category.create',
            ],
            [
                'name' => 'Update Category',
                'code' => 'category.update',
            ],
            [
                'name' => 'Create Unit',
                'code' => 'unit.create',
            ],
            [
                'name' => 'Update Unit',
                'code' => 'unit.update',
            ],
            [
                'name' => 'Create Product',
                'code' => 'product.create',
            ],
            [
                'name' => 'Update Product',
                'code' => 'product.update',
            ],
            [
                'name' => 'Create Supplier',
                'code' => 'supplier.create',
            ],
            [
                'name' => 'Update Supplier',
                'code' => 'supplier.update',
            ],
            [
                'name' => 'Create Purchase',
                'code' => 'purchase.create',
            ],
            [
                'name' => 'Receive Purchase',
                'code' => 'purchase.receive',
            ],
            [
                'name' => 'Adjust Inventory',
                'code' => 'inventory.adjust',
            ],
            [
                'name' => 'Create Sale',
                'code' => 'sale.create',
            ],
            [
                'name' => 'Cancel Sale',
                'code' => 'sale.cancel',
            ],
            [
                'name' => 'Update Company Profile',
                'code' => 'company_profile.update',
            ],
            [
                'name' => 'View Purchase Invoice',
                'code' => 'purchase.invoice.view',
            ],
            [
                'name' => 'Manage Purchase Invoice',
                'code' => 'purchase.invoice.manage',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'code' => $permission['code'],
                ],
                [
                    'name' => $permission['name'],
                ]
            );
        }

        $ownerRoleId = DB::table('roles')
            ->where('code', 'owner')
            ->value('id');

        $permissionIds = DB::table('permissions')
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')
                ->insertOrIgnore([
                    'role_id' => $ownerRoleId,
                    'permission_id' => $permissionId,
                ]);
        }
    }
}
