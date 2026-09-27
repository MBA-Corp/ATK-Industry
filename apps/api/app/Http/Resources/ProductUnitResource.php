<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'unit_code' => $this->unit->code,
            'unit_name' => $this->unit->name,
            'conversion_to_base' => (float) $this->conversion_to_base,
            'selling_price' => $this->selling_price,
            'is_base' => $this->is_base,
            'is_active' => $this->is_active,
        ];
    }
}
