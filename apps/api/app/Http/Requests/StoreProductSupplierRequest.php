<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists(
                    'suppliers',
                    'id'
                )->where(
                    fn ($query) => $query->where(
                        'is_active',
                        true
                    )
                ),
            ],

            'supplier_sku' => [
                'nullable',
                'string',
                'max:255',
            ],

            'last_purchase_price' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_preferred' => [
                'sometimes',
                'boolean',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (
                    $this->boolean(
                        'is_preferred'
                    )
                    && $this->has('is_active')
                    && ! $this->boolean(
                        'is_active'
                    )
                ) {
                    $validator->errors()->add(
                        'is_preferred',
                        'Preferred supplier relation must be active.'
                    );
                }
            },
        ];
    }
}
