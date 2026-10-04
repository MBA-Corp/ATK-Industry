<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'adjustment_number' => $this->adjustment_number,

            'adjustment_type' => $this->adjustment_type,

            'product' => [
                'id' => $this->product->id,

                'sku' => $this->product->sku,

                'name' => $this->product->name,
            ],

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

            'quantity' => $this->quantity,

            'conversion_to_base' => $this->conversion_to_base,

            'quantity_delta_base' => $this->quantity_delta_base,

            'quantity_before_base' => $this->quantity_before_base,

            'quantity_after_base' => $this->quantity_after_base,

            'reason' => $this->reason,

            'created_by' => $this->createdBy === null
                    ? null
                    : [
                        'id' => $this->createdBy->id,

                        'name' => $this->createdBy->name,

                        'username' => $this->createdBy
                            ->username,
                    ],

            'occurred_at' => $this->occurred_at,

            'created_at' => $this->created_at,
        ];
    }
}
