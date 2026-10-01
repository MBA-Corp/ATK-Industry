<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'product' => [
                'id' => $this->productSupplier
                    ->product
                    ->id,

                'sku' => $this->productSupplier
                    ->product
                    ->sku,

                'name' => $this->productSupplier
                    ->product
                    ->name,
            ],

            'product_supplier_id' => $this->product_supplier_id,

            'product_unit' => [
                'id' => $this->productUnit->id,

                'unit' => [
                    'id' => $this->productUnit
                        ->unit
                        ->id,

                    'code' => $this->productUnit
                        ->unit
                        ->code,

                    'name' => $this->productUnit
                        ->unit
                        ->name,
                ],
            ],

            'qty_ordered' => $this->qty_ordered,

            'qty_received' => $this->qty_received,

            'purchase_price' => $this->purchase_price,

            'line_total' => $this->line_total,
        ];
    }
}
