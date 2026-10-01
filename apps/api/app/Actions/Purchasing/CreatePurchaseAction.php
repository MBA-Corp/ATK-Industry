<?php

namespace App\Actions\Purchasing;

use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePurchaseAction
{
    public function execute(
        array $validated,
        ?int $createdBy = null
    ): Purchase {
        return DB::transaction(
            function () use (
                $validated,
                $createdBy
            ) {
                $subtotal = 0;

                foreach (
                    $validated['items'] as $item
                ) {
                    $subtotal +=
                        (int) round(
                            (float) $item[
                                'qty_ordered'
                            ]
                            * (int) $item[
                                'purchase_price'
                            ]
                        );
                }

                $operationalCost =
                    (int) (
                        $validated[
                            'operational_cost'
                        ] ?? 0
                    );

                $purchase = Purchase::create([
                    'purchase_number' => $this->generatePurchaseNumber(),

                    'supplier_id' => $validated['supplier_id'],

                    'supplier_invoice_number' => $validated[
                            'supplier_invoice_number'
                        ] ?? null,

                    'purchase_date' => $validated[
                            'purchase_date'
                        ],

                    'status' => 'DRAFT',

                    'subtotal' => $subtotal,

                    'operational_cost' => $operationalCost,

                    'grand_total' => $subtotal
                        + $operationalCost,

                    'notes' => $validated['notes']
                        ?? null,

                    'created_by' => $createdBy,
                ]);

                foreach (
                    $validated['items'] as $item
                ) {
                    $lineTotal = (int) round(
                        (float) $item[
                            'qty_ordered'
                        ]
                        * (int) $item[
                            'purchase_price'
                        ]
                    );

                    $purchase
                        ->items()
                        ->create([
                            'product_supplier_id' => $item[
                                    'product_supplier_id'
                                ],

                            'product_unit_id' => $item[
                                    'product_unit_id'
                                ],

                            'qty_ordered' => $item[
                                    'qty_ordered'
                                ],

                            'qty_received' => 0,

                            'purchase_price' => $item[
                                    'purchase_price'
                                ],

                            'line_total' => $lineTotal,
                        ]);
                }

                return $purchase->load([
                    'supplier:id,code,name',
                    'createdBy:id,name,username',

                    'items.productSupplier.product:id,sku,name',

                    'items.productUnit.unit:id,code,name',
                ]);
            },
            3
        );
    }

    private function generatePurchaseNumber(): string
    {
        return 'PUR-'
            .now()->format('Ymd')
            .'-'
            .Str::upper(
                (string) Str::ulid()
            );
    }
}
