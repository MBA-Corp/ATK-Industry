<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'payment_method' => $this
                ->payment_method,

            'amount' => $this->amount,

            'reference' => $this->reference,

            'paid_at' => $this->paid_at,
        ];
    }
}
