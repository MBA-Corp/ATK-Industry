<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'size' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'color' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'variant_label' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'units' => [
                'sometimes',
                'array',
                'min:1',
            ],

            'units.*.id' => [
                'nullable',
                'integer',
                'distinct',
            ],

            'units.*.unit_id' => [
                'required',
                'integer',
                'distinct',
                'exists:units,id',
            ],

            'units.*.conversion_to_base' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'units.*.selling_price' => [
                'required',
                'integer',
                'min:0',
            ],

            'units.*.is_base' => [
                'required',
                'boolean',
            ],

            'units.*.is_active' => [
                'sometimes',
                'boolean',
            ],

            'barcodes' => [
                'sometimes',
                'array',
            ],

            'barcodes.*.id' => [
                'nullable',
                'integer',
                'distinct',
            ],

            'barcodes.*.barcode' => [
                'required',
                'string',
                'max:255',
                'distinct',
            ],

            'barcodes.*.product_unit_id' => [
                'nullable',
                'integer',
            ],

            'barcodes.*.is_primary' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->has('units')) {
                    $units = $this->input('units', []);

                    $baseUnits = collect($units)->filter(
                        fn (array $unit) => ($unit['is_base'] ?? false) === true
                    );

                    if ($baseUnits->count() !== 1) {
                        $validator->errors()->add(
                            'units',
                            'Exactly one product unit must be the base unit.'
                        );
                    }

                    $baseUnit = $baseUnits->first();

                    if (
                        $baseUnit !== null
                        && (float) ($baseUnit['conversion_to_base'] ?? 0) !== 1.0
                    ) {
                        $validator->errors()->add(
                            'units',
                            'Base unit conversion_to_base must be 1.'
                        );
                    }
                }

                if ($this->has('barcodes')) {
                    $barcodes = $this->input('barcodes', []);

                    $primaryBarcodes = collect($barcodes)->filter(
                        fn (array $barcode) => ($barcode['is_primary'] ?? false) === true
                    );

                    if ($primaryBarcodes->count() > 1) {
                        $validator->errors()->add(
                            'barcodes',
                            'Only one barcode may be marked as primary.'
                        );
                    }

                    foreach ($barcodes as $index => $barcode) {
                        if (
                            ($barcode['product_unit_id'] ?? null) !== null
                        ) {
                            $validator->errors()->add(
                                "barcodes.{$index}.product_unit_id",
                                'Unit-specific barcode assignment is not supported yet.'
                            );
                        }
                    }
                }
            },
        ];
    }
}
