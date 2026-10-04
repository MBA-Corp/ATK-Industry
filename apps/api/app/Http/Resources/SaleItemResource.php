<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'product_id' => $this->product_id,

            'product_unit_id' => $this
                ->product_unit_id,

            'product_sku' => $this->product_sku,

            'product_name' => $this->product_name,

            'unit_code' => $this->unit_code,

            'quantity' => $this->quantity,

            'conversion_to_base' => $this
                ->conversion_to_base,

            'catalog_unit_price' => $this
                ->catalog_unit_price,

            'sale_unit_price' => $this
                ->sale_unit_price,

            'line_total' => $this->line_total,
        ];
    }
}
