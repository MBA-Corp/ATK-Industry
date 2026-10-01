<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSupplierResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,

            'supplier' => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
                'is_active' => $this->supplier
                    ->is_active,
            ],

            'supplier_sku' => $this->supplier_sku,

            'last_purchase_price' => $this->last_purchase_price,

            'is_preferred' => $this->is_preferred,

            'is_active' => $this->is_active,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
