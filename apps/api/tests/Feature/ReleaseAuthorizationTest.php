<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReleaseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_privileges_are_isolated_by_role_and_permission(): void
    {
        $this->seed(RbacSeeder::class);
        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::where('code', 'cashier')->sole());
        $manager = User::factory()->create();
        $role = Role::where('code', 'manager')->sole();
        $manager->roles()->attach($role);
        $role->permissions()->attach(Permission::where('code', 'report.sales.view')->sole());
        $range = '?date_from=2026-11-26&date_to=2026-11-26';
        $sales = '/api/v1/reports/sales';
        $finance = '/api/v1/reports/profit-loss';

        $this->actingAs($cashier)->getJson($sales.$range)->assertForbidden();
        $this->getJson($sales.'/export'.$range)->assertForbidden();
        $this->actingAs($manager)->getJson($sales.$range)->assertOk();
        $this->getJson($finance.$range)->assertForbidden();
        $this->getJson($finance.'/export'.$range)->assertForbidden();
        $this->postJson('/api/v1/expenses')->assertForbidden();
        $this->getJson('/api/v1/inventory/reorder-alerts')->assertForbidden();
        $this->putJson('/api/v1/inventory/1/planning')->assertForbidden();
        $this->postJson('/api/v1/catalog-imports/preview')->assertForbidden();
    }

    public function test_malformed_upload_cannot_create_catalog_or_import_audit_rows(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('code', 'owner')->sole());

        $this->actingAs($owner)->postJson('/api/v1/catalog-imports/preview', [
            'conversion_mode' => 'to_base',
            'zero_price_policy' => 'disable_sales',
            'file' => UploadedFile::fake()->createWithContent('synthetic.xlsx', 'Not an Excel archive.'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('catalog_imports', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
