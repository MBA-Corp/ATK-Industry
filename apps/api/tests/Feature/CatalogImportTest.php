<?php

namespace Tests\Feature;

use App\Actions\Catalog\ImportCatalogAction;
use App\Models\Product;
use App\Models\User;
use App\Support\CatalogWorkbookReader;
use App\Support\CostMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $override = []): array
    {
        return array_merge(['KODEITEM' => 'SYNTHETIC-001', 'NAMAITEM' => 'Synthetic pen',
            'JENIS' => 'Synthetic category', 'SATUAN1' => 'PCS', 'KONVERSI1' => '1',
            'HARGAJUAL1' => '1500', 'HARGAPOKOK1' => '1000', 'STOK' => '10',
            'STOKMIN' => '2', 'BARCODE1' => '0012345678'], $override);
    }

    public function test_preview_does_not_create_catalog_and_commit_is_idempotent(): void
    {
        $user = User::factory()->create();
        $rows = [$this->row()];
        $action = app(ImportCatalogAction::class);
        $preview = $action->preview($rows);
        $this->assertTrue($preview['commit_allowed']);
        $this->assertDatabaseCount('products', 0);
        $key = (string) Str::uuid();
        $result = $action->commit($rows, $key, $preview['hash'], $user->id);
        $replay = $action->commit($rows, $key, $preview['hash'], $user->id);
        $this->assertSame($result['id'], $replay['id']);
        $this->assertTrue($replay['replayed']);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('product_barcodes', ['barcode' => '0012345678']);
        $this->assertSame('10000.000000', CostMath::round((string) DB::table('inventory_costs')->value('inventory_value')));
    }

    public function test_duplicate_rows_are_reported_and_nothing_is_imported(): void
    {
        $action = app(ImportCatalogAction::class);
        $rows = [$this->row(), $this->row()];
        $preview = $action->preview($rows);
        $this->assertFalse($preview['commit_allowed']);
        $this->assertSame(1, $preview['failed']);
        try {
            $action->commit($rows, (string) Str::uuid(), $preview['hash'], User::factory()->create()->id);
            $this->fail('Invalid import accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('catalog_imports', 0);
        }
    }

    public function test_zero_prices_and_unknown_cost_are_explicit(): void
    {
        $rows = [$this->row(['HARGAJUAL1' => '0', 'HARGAPOKOK1' => '0'])];
        $action = app(ImportCatalogAction::class);
        $preview = $action->preview($rows);
        $this->assertCount(2, $preview['rows'][0]['warnings']);
        $action->commit($rows, (string) Str::uuid(), $preview['hash'], User::factory()->create()->id);
        $this->assertFalse(Product::first()->is_active);
        $this->assertNull(DB::table('inventory_costs')->value('inventory_value'));
    }

    public function test_changed_preview_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(ImportCatalogAction::class)->commit([$this->row()], (string) Str::uuid(), str_repeat('0', 64), 1);
    }

    public function test_import_endpoints_require_permission(): void
    {
        $this->postJson('/api/v1/catalog-imports/preview')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/catalog-imports/preview')->assertForbidden();
        $this->postJson('/api/v1/catalog-imports/commit')->assertForbidden();
    }

    public function test_xlsx_reader_reads_values_and_preserves_text_identifiers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'atk-xlsx');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Satuan" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>KODEITEM</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>000123</t></is></c></row></sheetData></worksheet>');
            $zip->close();
            $this->assertSame([['KODEITEM' => '000123']], app(CatalogWorkbookReader::class)->read($path));
        } finally {
            unlink($path);
        }
    }

    public function test_reader_rejects_formula_cells(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'atk-formula');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Satuan" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row><c r="A1"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>');
            $zip->close();
            $this->expectException(ValidationException::class);
            app(CatalogWorkbookReader::class)->read($path);
        } finally {
            unlink($path);
        }
    }
}
