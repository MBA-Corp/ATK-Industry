<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'sale_number' => $this->sale_number,

            'client_reference' => $this
                ->client_reference,

            'status' => $this->status,

            'subtotal' => $this->subtotal,

            'grand_total' => $this->grand_total,

            'sold_at' => $this->sold_at,

            'completed_at' => $this
                ->completed_at,

            'terminal' => $this->terminal === null
                ? null
                : [
                    'id' => $this->terminal->id,
                    'terminal_uuid' => $this
                        ->terminal
                        ->terminal_uuid,
                    'name' => $this->terminal->name,
                ],

            'cashier' => $this->cashier === null
                ? null
                : [
                    'id' => $this->cashier->id,
                    'name' => $this->cashier->name,
                    'username' => $this
                        ->cashier
                        ->username,
                ],

            'items' => SaleItemResource::collection(
                $this->whenLoaded('items')
            ),
            'payments' => SalePaymentResource::collection(
                $this->whenLoaded('payments')
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
