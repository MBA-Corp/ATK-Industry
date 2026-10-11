<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Purchase;
use App\Models\PurchaseInvoiceAttachment;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function createPurchase(): Purchase
    {
        $supplier = Supplier::create([
            'code' => 'SUP-INVOICE',
            'name' => 'Supplier Invoice Test',
            'is_active' => true,
        ]);

        return Purchase::create([
            'purchase_number' => 'PUR-INVOICE-001',
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-10-11',
            'status' => 'DRAFT',
        ]);
    }

    private function userWithPermissions(array $codes): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                ['name' => $code]
            );

            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_authorized_user_can_upload_pdf_invoice(): void
    {
        Storage::fake('local');

        $purchase = $this->createPurchase();
        $user = $this->userWithPermissions([
            'purchase.invoice.manage',
        ]);

        $this->actingAs($user)
            ->post(
                "/api/v1/purchases/{$purchase->id}/invoice",
                [
                    'invoice' => UploadedFile::fake()
                        ->create('invoice.pdf', 100, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.original_name',
                'invoice.pdf'
            )
            ->assertJsonPath(
                'data.purchase_id',
                $purchase->id
            );

        $attachment = PurchaseInvoiceAttachment::firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);

        $this->assertSame('local', $attachment->disk);
        $this->assertSame($user->id, $attachment->uploaded_by);
        $this->assertDatabaseCount('purchase_invoice_attachments', 1);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'DRAFT',
        ]);
    }

    public function test_new_upload_replaces_existing_invoice(): void
    {
        Storage::fake('local');

        $purchase = $this->createPurchase();
        $user = $this->userWithPermissions([
            'purchase.invoice.manage',
        ]);

        $url = "/api/v1/purchases/{$purchase->id}/invoice";

        $this->actingAs($user)
            ->post($url, [
                'invoice' => UploadedFile::fake()
                    ->create('old.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $old = PurchaseInvoiceAttachment::firstOrFail();
        $oldId = $old->id;
        $oldPath = $old->path;

        $this->actingAs($user)
            ->post($url, [
                'invoice' => UploadedFile::fake()
                    ->image('new.png', 100, 100),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.original_name', 'new.png');

        $current = PurchaseInvoiceAttachment::firstOrFail();

        $this->assertSame($oldId, $current->id);
        $this->assertNotSame($oldPath, $current->path);
        $this->assertDatabaseCount('purchase_invoice_attachments', 1);

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($current->path);
    }

    public function test_authorized_user_can_view_and_download_invoice(): void
    {
        Storage::fake('local');

        $purchase = $this->createPurchase();
        $user = $this->userWithPermissions([
            'purchase.invoice.view',
            'purchase.invoice.manage',
        ]);

        $url = "/api/v1/purchases/{$purchase->id}/invoice";

        $this->actingAs($user)
            ->post($url, [
                'invoice' => UploadedFile::fake()
                    ->create('supplier.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->actingAs($user)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.original_name', 'supplier.pdf')
            ->assertJsonMissingPath('data.path');

        $this->actingAs($user)
            ->get("{$url}/download")
            ->assertOk()
            ->assertDownload('supplier.pdf');
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('local');

        $purchase = $this->createPurchase();
        $user = $this->userWithPermissions([
            'purchase.invoice.manage',
        ]);

        $this->actingAs($user)
            ->post(
                "/api/v1/purchases/{$purchase->id}/invoice",
                [
                    'invoice' => UploadedFile::fake()
                        ->create('malware.txt', 100, 'text/plain'),
                ],
                ['Accept' => 'application/json']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice');

        $this->assertDatabaseCount('purchase_invoice_attachments', 0);
    }

    public function test_invoice_larger_than_five_mb_is_rejected(): void
    {
        Storage::fake('local');

        $purchase = $this->createPurchase();
        $user = $this->userWithPermissions([
            'purchase.invoice.manage',
        ]);

        $this->actingAs($user)
            ->post(
                "/api/v1/purchases/{$purchase->id}/invoice",
                [
                    'invoice' => UploadedFile::fake()
                        ->create('large.pdf', 5121, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice');

        $this->assertDatabaseCount('purchase_invoice_attachments', 0);
    }

    public function test_user_without_permissions_cannot_access_invoice(): void
    {
        Storage::fake('local');

        $purchase = $this->createPurchase();
        $user = User::factory()->create();

        $url = "/api/v1/purchases/{$purchase->id}/invoice";

        $this->actingAs($user)
            ->getJson($url)
            ->assertForbidden();

        $this->actingAs($user)
            ->get("{$url}/download")
            ->assertForbidden();

        $this->actingAs($user)
            ->post($url, [
                'invoice' => UploadedFile::fake()
                    ->create('invoice.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('purchase_invoice_attachments', 0);
    }

    public function test_guest_cannot_access_invoice_routes(): void
    {
        $purchase = $this->createPurchase();
        $url = "/api/v1/purchases/{$purchase->id}/invoice";

        $this->getJson($url)->assertUnauthorized();
        $this->getJson("{$url}/download")->assertUnauthorized();

        $this->postJson($url, [])->assertUnauthorized();
    }

    public function test_missing_invoice_returns_null_metadata_and_404_download(): void
    {
        $purchase = $this->createPurchase();
        $user = $this->userWithPermissions([
            'purchase.invoice.view',
        ]);

        $url = "/api/v1/purchases/{$purchase->id}/invoice";

        $this->actingAs($user)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($user)
            ->get("{$url}/download")
            ->assertNotFound();
    }

    public function test_upload_to_missing_purchase_does_not_store_file(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'purchase.invoice.manage',
        ]);

        $this->actingAs($user)
            ->post(
                '/api/v1/purchases/999999/invoice',
                [
                    'invoice' => UploadedFile::fake()
                        ->create('invoice.pdf', 100, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            )
            ->assertNotFound();

        $this->assertDatabaseCount('purchase_invoice_attachments', 0);

        $this->assertSame(
            [],
            Storage::disk('local')->allFiles('purchase-invoices')
        );
    }

    public function test_download_returns_404_when_stored_file_is_missing(): void
    {
        Storage::fake('local');

        $purchase = $this->createPurchase();
        $user = $this->userWithPermissions([
            'purchase.invoice.manage',
            'purchase.invoice.view',
        ]);

        $url = "/api/v1/purchases/{$purchase->id}/invoice";

        $this->actingAs($user)
            ->post($url, [
                'invoice' => UploadedFile::fake()
                    ->create('invoice.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $attachment = PurchaseInvoiceAttachment::firstOrFail();

        Storage::disk('local')->delete($attachment->path);

        $this->actingAs($user)
            ->get("{$url}/download")
            ->assertNotFound();
    }
}
