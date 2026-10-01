<?php

namespace App\Http\Requests;

use App\Models\ProductSupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'last_purchase_price' => [
                'sometimes',
                'required',
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
                $productSupplier = ProductSupplier::query()
                    ->with('supplier:id,is_active')
                    ->where(
                        'product_id',
                        $this->route('productId')
                    )
                    ->find(
                        $this->route(
                            'productSupplierId'
                        )
                    );

                if ($productSupplier === null) {
                    return;
                }

                $resultingActive = $this->has(
                    'is_active'
                )
                    ? $this->boolean('is_active')
                    : $productSupplier->is_active;

                $resultingPreferred = $this->has(
                    'is_preferred'
                )
                    ? $this->boolean(
                        'is_preferred'
                    )
                    : $productSupplier->is_preferred;

                if (
                    $resultingActive
                    && ! $productSupplier
                        ->supplier
                        ?->is_active
                ) {
                    $validator->errors()->add(
                        'is_active',
                        'Active product supplier relation requires an active supplier.'
                    );
                }

                if (
                    $this->has('is_preferred')
                    && $this->boolean('is_preferred')
                    && ! $resultingActive
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
