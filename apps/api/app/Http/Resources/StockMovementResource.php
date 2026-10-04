<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'movement_type' => $this->movement_type,

            'source' => [
                'type' => $this->source_type,

                'id' => $this->source_id,
            ],

            'product_unit_id' => $this->product_unit_id,

            'source_quantity' => $this->source_quantity,

            'conversion_to_base' => $this->conversion_to_base,

            'quantity_delta_base' => $this->quantity_delta_base,

            'quantity_before_base' => $this->quantity_before_base,

            'quantity_after_base' => $this->quantity_after_base,

            'actor' => $this->actor === null
                    ? null
                    : [
                        'id' => $this->actor->id,

                        'name' => $this->actor->name,

                        'username' => $this->actor
                            ->username,
                    ],

            'terminal_id' => $this->terminal_id,

            'reason' => $this->reason,

            'occurred_at' => $this->occurred_at,

            'created_at' => $this->created_at,
        ];
    }
}
