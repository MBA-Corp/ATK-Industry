<?php

namespace App\Actions\Catalog;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Unit;
use App\Support\CostMath;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportCatalogAction
{
    public function preview(array $rows): array
    {
        if (count($rows) === 0 || count($rows) > 3000) {
            throw ValidationException::withMessages(['file' => 'Supply between 1 and 3000 data rows.']);
        }
        $seenSkus = [];
        $seenBarcodes = [];
        $results = [];
        foreach ($rows as $index => $row) {
            $errors = [];
            $warnings = [];
            $sku = trim((string) ($row['KODEITEM'] ?? ''));
            $name = trim((string) ($row['NAMAITEM'] ?? ''));
            $validator = Validator::make($row, [
                'KODEITEM' => 'required|string|max:255',
                'NAMAITEM' => 'required|string|max:255',
                'JENIS' => 'nullable|string|max:255',
                'STOK' => ['required', 'regex:/\A\d{1,12}(?:\.\d{1,6})?\z/'],
                'STOKMIN' => ['nullable', 'regex:/\A\d{1,12}(?:\.\d{1,6})?\z/'],
                'HARGAPOKOK1' => ['nullable', 'regex:/\A\d{1,12}(?:\.\d{1,6})?\z/'],
            ]);
            $errors = $validator->errors()->all();
            if (isset($seenSkus[mb_strtolower($sku)]) || Product::whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->exists()) {
                $errors[] = 'SKU already exists or is repeated.';
            }
            $seenSkus[mb_strtolower($sku)] = true;
            $units = [];
            $unitCodes = [];
            for ($i = 1; $i <= 4; $i++) {
                $code = trim((string) ($row['SATUAN'.$i] ?? ''));
                if ($code === '' && $i !== 1) {
                    if (($row['BARCODE'.$i] ?? '') !== '') {
                        $errors[] = "BARCODE{$i} has no unit.";
                    }

                    continue;
                }
                $conversion = (string) ($row['KONVERSI'.$i] ?? '');
                $price = (string) ($row['HARGAJUAL'.$i] ?? '');
                $barcode = trim((string) ($row['BARCODE'.$i] ?? ''));
                $v = Validator::make(compact('code', 'conversion', 'price', 'barcode'), [
                    'code' => 'required|string|max:50',
                    'conversion' => ['required', 'regex:/\A\d{1,12}(?:\.\d{1,6})?\z/', 'numeric', 'gt:0'],
                    'price' => ['required', 'regex:/\A\d{1,15}\z/'],
                    'barcode' => 'nullable|string|max:255',
                ]);
                if ($v->fails()) {
                    $errors = array_merge($errors, $v->errors()->all());

                    continue;
                }
                if (($i === 1 && DecimalMath::normalize($conversion) !== '1.000000') || isset($unitCodes[mb_strtolower($code)])) {
                    $errors[] = 'Base conversion must be 1 and unit codes must be distinct.';
                }
                $unitCodes[mb_strtolower($code)] = true;
                if ($barcode !== '') {
                    if (isset($seenBarcodes[mb_strtolower($barcode)]) || ProductBarcode::whereRaw('LOWER(barcode) = ?', [mb_strtolower($barcode)])->exists()) {
                        $errors[] = 'Barcode already exists or is repeated.';
                    }
                    $seenBarcodes[mb_strtolower($barcode)] = true;
                }
                if ((int) $price === 0) {
                    $warnings[] = "SATUAN{$i}: zero selling price; sales disabled for this unit/product.";
                }
                $units[] = ['code' => $code, 'conversion' => $conversion, 'price' => (int) $price, 'barcode' => $barcode, 'base' => $i === 1];
            }
            $cost = (string) ($row['HARGAPOKOK1'] ?? '');
            if ($cost === '' || ($validator->passes() && bccomp($cost, '0', 6) === 0)) {
                $warnings[] = 'Unknown opening cost; valuation and actual margin unavailable.';
                $cost = null;
            }
            $results[] = ['row' => $index + 2, 'sku' => $sku, 'name' => $name, 'errors' => $errors,
                'warnings' => $warnings, 'units' => $units, 'cost' => $cost, 'source' => $row];
        }
        $failed = count(array_filter($results, fn ($r) => $r['errors'] !== []));

        return ['hash' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'total' => count($rows), 'valid' => count($rows) - $failed, 'failed' => $failed,
            'commit_allowed' => $failed === 0, 'rows' => $results];
    }

    public function commit(array $rows, string $key, string $previewHash, int $actor): array
    {
        $hash = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        if (! hash_equals($hash, $previewHash)) {
            throw ValidationException::withMessages(['preview_hash' => 'Workbook changed after preview.']);
        }

        return DB::transaction(function () use ($rows, $key, $hash, $actor) {
            $existing = DB::table('catalog_imports')->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                abort_unless(hash_equals($existing->content_hash, $hash), 409, 'Idempotency key has different content.');

                return ['id' => $existing->id, 'imported' => $existing->row_count, 'replayed' => true];
            }
            abort_if(DB::table('catalog_imports')->where('content_hash', $hash)->exists(), 409, 'Workbook was already imported.');
            $preview = $this->preview($rows);
            if (! $preview['commit_allowed']) {
                throw ValidationException::withMessages(['rows' => 'Preview contains invalid rows; nothing imported.']);
            }
            $id = DB::table('catalog_imports')->insertGetId([
                'idempotency_key' => $key, 'content_hash' => $hash, 'actor_id' => $actor,
                'row_count' => count($rows), 'created_at' => now(),
            ]);
            foreach ($preview['rows'] as $row) {
                $category = trim((string) ($row['source']['JENIS'] ?? ''));
                if ($category !== '' && Category::where('name', $category)->where('is_active', false)->exists()) {
                    throw ValidationException::withMessages(['category' => 'Mapped category is inactive.']);
                }
                $categoryId = $category === '' ? null : Category::firstOrCreate(['name' => $category], ['is_active' => true])->id;
                $product = Product::create(['sku' => $row['sku'], 'name' => $row['name'],
                    'category_id' => $categoryId, 'is_active' => $row['units'][0]['price'] > 0]);
                $base = null;
                foreach ($row['units'] as $unit) {
                    $master = Unit::firstOrCreate(['code' => $unit['code']], ['name' => $unit['code'], 'is_active' => true]);
                    if (! $master->is_active) {
                        throw ValidationException::withMessages(['units' => 'Mapped unit is inactive.']);
                    }
                    $pu = $product->productUnits()->create([
                        'unit_id' => $master->id, 'conversion_to_base' => $unit['conversion'],
                        'selling_price' => $unit['price'], 'is_base' => $unit['base'],
                        'is_active' => $unit['base'] || $unit['price'] > 0,
                    ]);
                    if ($unit['base']) {
                        $base = $pu;
                    }
                    if ($unit['barcode'] !== '') {
                        $product->barcodes()->create(['barcode' => $unit['barcode'],
                            'product_unit_id' => $pu->id, 'is_primary' => $unit['base']]);
                    }
                }
                $rowId = DB::table('catalog_import_rows')->insertGetId([
                    'catalog_import_id' => $id, 'row_number' => $row['row'], 'product_id' => $product->id,
                    'source_metadata' => json_encode($row['source'], JSON_THROW_ON_ERROR),
                ]);
                $stock = DecimalMath::normalize((string) $row['source']['STOK']);
                if ($stock !== '0.000000') {
                    app(ApplyStockMovementAction::class)->execute(
                        $product->id, 'OPENING', 'CATALOG_IMPORT_ROW', $rowId, $stock,
                        productUnitId: $base->id, sourceQuantity: $stock, conversionToBase: '1',
                        actorUserId: $actor, reason: 'Catalog opening stock (provisional source)',
                        incomingValue: $row['cost'] === null ? null : CostMath::round(bcmul($stock, $row['cost'], 12)),
                    );
                }
            }

            return ['id' => $id, 'imported' => count($rows), 'replayed' => false];
        }, 3);
    }
}
