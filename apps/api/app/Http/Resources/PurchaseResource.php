<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'purchase_number' => $this->purchase_number,

            'supplier' => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ],

            'supplier_invoice_number' => $this->supplier_invoice_number,

            'purchase_date' => $this->purchase_date
                ?->toDateString(),

            'status' => $this->status,

            'subtotal' => $this->subtotal,

            'operational_cost' => $this->operational_cost,

            'grand_total' => $this->grand_total,

            'notes' => $this->notes,

            'created_by' => $this->createdBy === null
                    ? null
                    : [
                        'id' => $this->createdBy->id,

                        'name' => $this->createdBy->name,

                        'username' => $this->createdBy
                            ->username,
                    ],

            'received_at' => $this->received_at,

            'items' => PurchaseItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
