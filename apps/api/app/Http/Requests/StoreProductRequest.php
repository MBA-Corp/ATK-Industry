<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],

            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'is_active',
                            true
                        )
                    ),
            ],

            'size' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'variant_label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],

            'units' => ['required', 'array', 'min:1'],

            'units.*.unit_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('units', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'is_active',
                            true
                        )
                    ),
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
                $units = $this->input('units', []);

                $baseUnits = collect($units)
                    ->filter(
                        fn (array $unit) => filter_var(
                            $unit['is_base'] ?? false,
                            FILTER_VALIDATE_BOOLEAN
                        )
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

                if (
                    $baseUnit !== null
                    && array_key_exists('is_active', $baseUnit)
                    && ! filter_var(
                        $baseUnit['is_active'],
                        FILTER_VALIDATE_BOOLEAN
                    )
                ) {
                    $validator->errors()->add(
                        'units',
                        'Base product unit must be active.'
                    );
                }

                $barcodes = $this->input('barcodes', []);

                $primaryBarcodes = collect($barcodes)
                    ->filter(
                        fn (array $barcode) => filter_var(
                            $barcode['is_primary'] ?? false,
                            FILTER_VALIDATE_BOOLEAN
                        )
                    );

                if ($primaryBarcodes->count() > 1) {
                    $validator->errors()->add(
                        'barcodes',
                        'Only one barcode may be marked as primary.'
                    );
                }

                foreach ($barcodes as $index => $barcode) {
                    if (($barcode['product_unit_id'] ?? null) !== null) {
                        $validator->errors()->add(
                            "barcodes.{$index}.product_unit_id",
                            'Unit-specific barcode assignment is not supported during product creation.'
                        );
                    }
                }
            },
        ];
    }
}
