<?php

namespace App\Http\Requests;

use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'adjustment_type' => [
                'required',
                Rule::in([
                    'STOCK_OPNAME',
                    'DAMAGE',
                    'LOSS',
                    'ADJUSTMENT',
                ]),
            ],

            'product_unit_id' => [
                'required',
                'integer',
                'exists:product_units,id',
            ],

            'quantity' => [
                'required',
                'numeric',
            ],

            'reason' => [
                'required',
                'string',
                'max:2000',
            ],

            'occurred_at' => [
                'sometimes',
                'date',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $productUnit =
                    ProductUnit::query()
                        ->whereKey(
                            $this->input(
                                'product_unit_id'
                            )
                        )
                        ->where(
                            'product_id',
                            $this->route(
                                'productId'
                            )
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->first();

                if ($productUnit === null) {
                    $validator
                        ->errors()
                        ->add(
                            'product_unit_id',
                            'Product unit must be active and belong to the selected product.'
                        );
                }

                $type = $this->input(
                    'adjustment_type'
                );

                $quantity = (float) $this->input(
                    'quantity',
                    0
                );

                if (
                    in_array(
                        $type,
                        [
                            'STOCK_OPNAME',
                            'DAMAGE',
                            'LOSS',
                        ],
                        true
                    )
                    && $quantity < 0
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'quantity',
                            'Quantity cannot be negative for this adjustment type.'
                        );
                }

                if (
                    in_array(
                        $type,
                        [
                            'DAMAGE',
                            'LOSS',
                        ],
                        true
                    )
                    && $quantity <= 0
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'quantity',
                            'Quantity must be greater than zero.'
                        );
                }

                if (
                    $type === 'ADJUSTMENT'
                    && $quantity == 0
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'quantity',
                            'Adjustment quantity cannot be zero.'
                        );
                }
            },
        ];
    }
}
