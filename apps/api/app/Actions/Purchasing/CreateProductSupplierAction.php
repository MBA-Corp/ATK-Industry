<?php

namespace App\Actions\Purchasing;

use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;

class CreateProductSupplierAction
{
    public function execute(
        int $productId,
        array $validated,
        ?int $changedBy = null
    ): ProductSupplier {
        return DB::transaction(
            function () use (
                $productId,
                $validated,
                $changedBy
            ) {
                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($productId);

                $isPreferred = $validated[
                    'is_preferred'
                ] ?? false;

                if ($isPreferred) {
                    $product
                        ->productSuppliers()
                        ->where(
                            'is_preferred',
                            true
                        )
                        ->update([
                            'is_preferred' => false,
                        ]);
                }

                $productSupplier = $product
                    ->productSuppliers()
                    ->create([
                        'supplier_id' => $validated[
                                'supplier_id'
                            ],

                        'supplier_sku' => $validated[
                                'supplier_sku'
                            ] ?? null,

                        'last_purchase_price' => $validated[
                                'last_purchase_price'
                            ] ?? null,

                        'is_preferred' => $isPreferred,

                        'is_active' => $validated[
                                'is_active'
                            ] ?? true,
                    ]);

                if (
                    $productSupplier
                        ->last_purchase_price
                    !== null
                ) {
                    $productSupplier
                        ->priceHistories()
                        ->create([
                            'old_price' => null,

                            'new_price' => $productSupplier
                                ->last_purchase_price,

                            'changed_by' => $changedBy,
                        ]);
                }

                return $productSupplier->load(
                    'supplier:id,code,name,is_active'
                );
            },
            3
        );
    }
}
