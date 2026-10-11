<?php

namespace App\Http\Requests;

use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_reference' => [
                'nullable',
                'uuid',
                Rule::unique(
                    'sales',
                    'client_reference'
                ),
            ],

            'terminal_id' => [
                'required',
                'integer',
                Rule::exists(
                    'terminals',
                    'id'
                )->where(
                    fn ($query) => $query->where(
                        'is_active',
                        true
                    )
                ),
            ],

            'sold_at' => [
                'sometimes',
                'date',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_unit_id' => [
                'required',
                'integer',
                'exists:product_units,id',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/\A\d{1,12}(?:\.\d{1,6})?\z/',
            ],

            'items.*.sale_unit_price' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'payments' => [
                'required',
                'array',
                'min:1',
                'max:2',
            ],

            'payments.*.payment_method' => [
                'required',
                'string',
                'distinct',
                Rule::in([
                    'CASH',
                    'QRIS',
                ]),
            ],

            'payments.*.amount' => [
                'required',
                'integer',
                'gt:0',
            ],

            'payments.*.reference' => [
                'nullable',
                'string',
                'max:255',
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
                foreach (
                    $this->input('items', []) as $index => $item
                ) {
                    if (
                        ! isset(
                            $item['product_unit_id']
                        )
                    ) {
                        continue;
                    }

                    $productUnit = ProductUnit::query()
                        ->with('product:id,is_active')
                        ->whereKey(
                            $item['product_unit_id']
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->first();

                    if (
                        $productUnit === null
                        || ! $productUnit->product
                            ?->is_active
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "items.{$index}.product_unit_id",
                                'Product unit and product must be active.'
                            );

                        continue;
                    }

                    if (
                        $productUnit->selling_price <= 0
                        && ! isset(
                            $item['sale_unit_price']
                        )
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "items.{$index}.product_unit_id",
                                'Product unit must have a valid selling price.'
                            );
                    }
                }
            },
        ];
    }
}
