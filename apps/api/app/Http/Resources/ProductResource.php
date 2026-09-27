<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category === null
                ? null
                : [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ],
            'size' => $this->size,
            'color' => $this->color,
            'variant_label' => $this->variant_label,
            'is_active' => $this->is_active,
            'units' => ProductUnitResource::collection(
                $this->productUnits
            ),
            'barcodes' => ProductBarcodeResource::collection(
                $this->barcodes
            ),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
