<?php

namespace App\Http\Requests;

use App\Models\ProductSupplier;
use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
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

            'supplier_invoice_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'purchase_date' => [
                'required',
                'date',
            ],

            'operational_cost' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_supplier_id' => [
                'required',
                'integer',
                'distinct',
                'exists:product_suppliers,id',
            ],

            'items.*.product_unit_id' => [
                'required',
                'integer',
                'exists:product_units,id',
            ],

            'items.*.qty_ordered' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.purchase_price' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $supplierId = (int) $this->input(
                    'supplier_id'
                );

                foreach (
                    $this->input('items', []) as $index => $item
                ) {
                    if (
                        ! isset(
                            $item[
                                'product_supplier_id'
                            ],
                            $item['product_unit_id']
                        )
                    ) {
                        continue;
                    }

                    $productSupplier =
                        ProductSupplier::query()
                            ->whereKey(
                                $item[
                                    'product_supplier_id'
                                ]
                            )
                            ->where(
                                'supplier_id',
                                $supplierId
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->first();

                    if ($productSupplier === null) {
                        $validator
                            ->errors()
                            ->add(
                                "items.{$index}.product_supplier_id",
                                'Product supplier relation must be active and belong to the selected supplier.'
                            );

                        continue;
                    }

                    $validProductUnit =
                        ProductUnit::query()
                            ->whereKey(
                                $item[
                                    'product_unit_id'
                                ]
                            )
                            ->where(
                                'product_id',
                                $productSupplier
                                    ->product_id
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->exists();

                    if (! $validProductUnit) {
                        $validator
                            ->errors()
                            ->add(
                                "items.{$index}.product_unit_id",
                                'Product unit must be active and belong to the selected product supplier.'
                            );
                    }
                }
            },
        ];
    }
}
