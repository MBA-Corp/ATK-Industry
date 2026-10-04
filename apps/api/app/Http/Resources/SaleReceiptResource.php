<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReceiptResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'receipt_number' => $this->sale_number,

            'sale_id' => $this->id,

            'status' => $this->status,

            'sold_at' => $this->sold_at,

            'completed_at' => $this->completed_at,

            'terminal' => [
                'id' => $this->terminal->id,

                'name' => $this->terminal->name,

                'terminal_uuid' => $this
                    ->terminal
                    ->terminal_uuid,
            ],

            'cashier' => [
                'id' => $this->cashier->id,

                'name' => $this->cashier->name,

                'username' => $this
                    ->cashier
                    ->username,
            ],

            'items' => $this->items->map(
                fn ($item) => [
                    'product_sku' => $item
                        ->product_sku,

                    'product_name' => $item
                        ->product_name,

                    'unit_code' => $item
                        ->unit_code,

                    'quantity' => $item
                        ->quantity,

                    'unit_price' => $item
                        ->sale_unit_price,

                    'line_total' => $item
                        ->line_total,
                ]
            )->values(),

            'subtotal' => $this->subtotal,

            'grand_total' => $this->grand_total,

            'payments' => $this->payments->map(
                fn ($payment) => [
                    'payment_method' => $payment
                        ->payment_method,

                    'amount' => $payment->amount,

                    'reference' => $payment
                        ->reference,

                    'paid_at' => $payment
                        ->paid_at,
                ]
            )->values(),

            'cancellation' => $this->cancellation === null
                    ? null
                    : [
                        'reason' => $this
                            ->cancellation
                            ->reason,

                        'cancelled_at' => $this
                            ->cancellation
                            ->cancelled_at,

                        'cancelled_by' => $this
                            ->cancellation
                            ->cancelledBy === null
                                ? null
                                : [
                                    'id' => $this
                                        ->cancellation
                                        ->cancelledBy
                                        ->id,

                                    'name' => $this
                                        ->cancellation
                                        ->cancelledBy
                                        ->name,

                                    'username' => $this
                                        ->cancellation
                                        ->cancelledBy
                                        ->username,
                                ],
                    ],
        ];
    }
}
