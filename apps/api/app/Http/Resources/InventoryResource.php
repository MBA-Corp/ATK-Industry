<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $baseProductUnit = $this
            ->productUnits
            ->firstWhere(
                'is_base',
                true
            );

        return [
            'product' => [
                'id' => $this->id,
                'sku' => $this->sku,
                'name' => $this->name,
                'is_active' => $this->is_active,
            ],

            'quantity_base' => $this->inventoryBalance
                ?->quantity_base
                ?? '0.000000',

            'base_unit' => $baseProductUnit === null
                    ? null
                    : [
                        'product_unit_id' => $baseProductUnit->id,

                        'unit_id' => $baseProductUnit
                            ->unit
                            ?->id,

                        'code' => $baseProductUnit
                            ->unit
                            ?->code,

                        'name' => $baseProductUnit
                            ->unit
                            ?->name,
                    ],
        ];
    }
}
