<?php

namespace App\Http\Requests;

use App\Models\ProductUnit;
use App\Support\DecimalMath;
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
                'regex:/\A-?\d{1,12}(?:\.\d{1,6})?\z/',
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $productUnit = ProductUnit::query()
                    ->whereKey($this->input('product_unit_id'))
                    ->where('product_id', $this->route('productId'))
                    ->where('is_active', true)
                    ->first();

                if ($productUnit === null) {
                    $validator->errors()->add(
                        'product_unit_id',
                        'Product unit must be active and belong to the selected product.'
                    );
                }

                $type = $this->input('adjustment_type');
                $quantity = DecimalMath::normalize(
                    (string) $this->input('quantity')
                );
                $isNegative = str_starts_with($quantity, '-');
                $isZero = $quantity === '0.000000';

                if (
                    in_array($type, ['STOCK_OPNAME', 'DAMAGE', 'LOSS'], true)
                    && $isNegative
                ) {
                    $validator->errors()->add(
                        'quantity',
                        'Quantity cannot be negative for this adjustment type.'
                    );
                }

                if (
                    in_array($type, ['DAMAGE', 'LOSS'], true)
                    && $isZero
                ) {
                    $validator->errors()->add(
                        'quantity',
                        'Quantity must be greater than zero.'
                    );
                }

                if ($type === 'ADJUSTMENT' && $isZero) {
                    $validator->errors()->add(
                        'quantity',
                        'Adjustment quantity cannot be zero.'
                    );
                }
            },
        ];
    }
}
