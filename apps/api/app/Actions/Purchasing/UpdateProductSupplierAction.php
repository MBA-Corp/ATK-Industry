<?php

namespace App\Actions\Purchasing;

use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;

class UpdateProductSupplierAction
{
    public function execute(
        int $productId,
        int $productSupplierId,
        array $validated,
        ?int $changedBy = null
    ): ProductSupplier {
        return DB::transaction(
            function () use (
                $productId,
                $productSupplierId,
                $validated,
                $changedBy
            ) {
                Product::query()
                    ->lockForUpdate()
                    ->findOrFail($productId);

                $productSupplier = ProductSupplier::query()
                    ->where(
                        'product_id',
                        $productId
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $productSupplierId
                    );

                $oldPrice = $productSupplier
                    ->last_purchase_price;

                $resultingActive = array_key_exists(
                    'is_active',
                    $validated
                )
                    ? (bool) $validated[
                        'is_active'
                    ]
                    : $productSupplier->is_active;

                $resultingPreferred = array_key_exists(
                    'is_preferred',
                    $validated
                )
                    ? (bool) $validated[
                        'is_preferred'
                    ]
                    : $productSupplier
                        ->is_preferred;

                if (! $resultingActive) {
                    $resultingPreferred = false;
                }

                if ($resultingPreferred) {
                    ProductSupplier::query()
                        ->where(
                            'product_id',
                            $productId
                        )
                        ->whereKeyNot(
                            'id',
                            '!=',
                            $productSupplier->id
                        )
                        ->where(
                            'is_preferred',
                            true
                        )
                        ->update([
                            'is_preferred' => false,
                        ]);
                }

                $updates = [
                    'is_active' => $resultingActive,

                    'is_preferred' => $resultingPreferred,
                ];

                if (
                    array_key_exists(
                        'supplier_sku',
                        $validated
                    )
                ) {
                    $updates['supplier_sku'] =
                        $validated[
                            'supplier_sku'
                        ];
                }

                if (
                    array_key_exists(
                        'last_purchase_price',
                        $validated
                    )
                ) {
                    $updates[
                        'last_purchase_price'
                    ] = (int) $validated[
                        'last_purchase_price'
                    ];
                }

                $productSupplier->update(
                    $updates
                );

                if (
                    array_key_exists(
                        'last_purchase_price',
                        $validated
                    )
                ) {
                    $newPrice = (int) $validated[
                        'last_purchase_price'
                    ];

                    if (
                        $oldPrice === null
                        || (int) $oldPrice
                            !== $newPrice
                    ) {
                        $productSupplier
                            ->priceHistories()
                            ->create([
                                'old_price' => $oldPrice,

                                'new_price' => $newPrice,

                                'changed_by' => $changedBy,
                            ]);
                    }
                }

                return $productSupplier
                    ->refresh()
                    ->load(
                        'supplier:id,code,name,is_active'
                    );
            },
            3
        );
    }
}
