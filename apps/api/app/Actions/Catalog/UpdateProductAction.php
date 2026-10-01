<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProductAction
{
    public function execute(
        int $productId,
        array $validated,
        ?int $changedBy = null
    ): Product {
        return DB::transaction(function () use (
            $productId,
            $validated,
            $changedBy
        ) {
            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->updateProduct(
                $product,
                $validated
            );

            if (array_key_exists('units', $validated)) {
                $this->syncUnits(
                    $product,
                    $validated['units'],
                    $changedBy
                );
            }

            if (array_key_exists('barcodes', $validated)) {
                $this->syncBarcodes(
                    $product,
                    $validated['barcodes']
                );
            }

            return $product->fresh([
                'category:id,name',
                'productUnits.unit:id,code,name',
                'barcodes',
            ]);
        }, 3);
    }

    private function updateProduct(
        Product $product,
        array $validated
    ): void {
        $fields = [
            'sku',
            'name',
            'category_id',
            'size',
            'color',
            'variant_label',
            'is_active',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $product->{$field} = $validated[$field];
            }
        }

        if ($product->isDirty()) {
            $product->save();
        }
    }

    private function syncUnits(
        Product $product,
        array $units,
        ?int $changedBy
    ): void {
        $existingUnits = $product
            ->productUnits()
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $keptIds = [];

        foreach ($units as $index => $unitData) {
            if (! empty($unitData['id'])) {
                $productUnit = $existingUnits->get(
                    (int) $unitData['id']
                );

                if ($productUnit === null) {
                    throw ValidationException::withMessages([
                        "units.{$index}.id" => 'Product unit does not belong to this product.',
                    ]);
                }

                if (
                    (int) $productUnit->unit_id
                    !== (int) $unitData['unit_id']
                ) {
                    throw ValidationException::withMessages([
                        "units.{$index}.unit_id" => 'Existing product unit unit_id cannot be changed. Add a new unit instead.',
                    ]);
                }

                $oldPrice = (int) $productUnit->selling_price;
                $newPrice = (int) $unitData['selling_price'];

                $productUnit->update([
                    'conversion_to_base' => $unitData['conversion_to_base'],

                    'selling_price' => $newPrice,

                    'is_base' => $unitData['is_base'],

                    'is_active' => $unitData['is_active'] ?? true,
                ]);

                if ($oldPrice !== $newPrice) {
                    $productUnit
                        ->priceHistories()
                        ->create([
                            'old_price' => $oldPrice,
                            'new_price' => $newPrice,
                            'changed_by' => $changedBy,
                        ]);
                }
            } else {
                $productUnit = $product
                    ->productUnits()
                    ->create([
                        'unit_id' => $unitData['unit_id'],

                        'conversion_to_base' => $unitData['conversion_to_base'],

                        'selling_price' => $unitData['selling_price'],

                        'is_base' => $unitData['is_base'],

                        'is_active' => $unitData['is_active'] ?? true,
                    ]);

                $productUnit
                    ->priceHistories()
                    ->create([
                        'old_price' => null,
                        'new_price' => (int) $unitData['selling_price'],
                        'changed_by' => $changedBy,
                    ]);
            }

            $keptIds[] = $productUnit->id;
        }

        $product
            ->productUnits()
            ->whereNotIn('id', $keptIds)
            ->update([
                'is_active' => false,
                'is_base' => false,
            ]);
    }

    private function syncBarcodes(
        Product $product,
        array $barcodes
    ): void {
        $existingBarcodes = $product
            ->barcodes()
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $keptIds = [];

        foreach ($barcodes as $index => $barcodeData) {
            if (! empty($barcodeData['id'])) {
                $barcode = $existingBarcodes->get(
                    (int) $barcodeData['id']
                );

                if ($barcode === null) {
                    throw ValidationException::withMessages([
                        "barcodes.{$index}.id" => 'Barcode does not belong to this product.',
                    ]);
                }

                $barcode->update([
                    'barcode' => $barcodeData['barcode'],

                    'product_unit_id' => null,

                    'is_primary' => $barcodeData['is_primary'] ?? false,
                ]);
            } else {
                $barcode = $product
                    ->barcodes()
                    ->create([
                        'barcode' => $barcodeData['barcode'],

                        'product_unit_id' => null,

                        'is_primary' => $barcodeData['is_primary'] ?? false,
                    ]);
            }

            $keptIds[] = $barcode->id;
        }

        if ($keptIds === []) {
            $product->barcodes()->delete();

            return;
        }

        $product
            ->barcodes()
            ->whereNotIn('id', $keptIds)
            ->delete();
    }
}
