<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
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
                $product = Product::query()
                    ->with([
                        'category:id,is_active',
                        'productUnits.unit:id,is_active',
                    ])
                    ->find($this->route('productId'));

                if ($product === null) {
                    return;
                }

                $resultingProductActive = $this->has('is_active')
                    ? $this->boolean('is_active')
                    : $product->is_active;

                $isActivating = $this->has('is_active')
                    && $this->boolean('is_active')
                    && ! $product->is_active;

                $this->validateCategoryIntegrity(
                    $validator,
                    $product,
                    $resultingProductActive,
                    $isActivating
                );

                $this->validateUnitIntegrity(
                    $validator,
                    $product,
                    $resultingProductActive,
                    $isActivating
                );

                $this->validateBarcodes($validator);
            },
        ];
    }

    private function validateCategoryIntegrity(
        Validator $validator,
        Product $product,
        bool $resultingProductActive,
        bool $isActivating
    ): void {
        if ($this->has('category_id')) {
            $categoryId = $this->input('category_id');

            if (
                $resultingProductActive
                && $categoryId !== null
                && ! Category::query()
                    ->whereKey($categoryId)
                    ->where('is_active', true)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'category_id',
                    'Active products cannot use an inactive category.'
                );
            }

            return;
        }

        if (
            $isActivating
            && $product->category_id !== null
            && ! $product->category?->is_active
        ) {
            $validator->errors()->add(
                'category_id',
                'Product cannot be activated while its category is inactive.'
            );
        }
    }

    private function validateUnitIntegrity(
        Validator $validator,
        Product $product,
        bool $resultingProductActive,
        bool $isActivating
    ): void {
        if ($this->has('units')) {
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
                $resultingProductActive
                && $baseUnit !== null
                && array_key_exists('is_active', $baseUnit)
                && ! filter_var(
                    $baseUnit['is_active'],
                    FILTER_VALIDATE_BOOLEAN
                )
            ) {
                $validator->errors()->add(
                    'units',
                    'Active products must have an active base unit.'
                );
            }

            $unitIds = collect($units)
                ->pluck('unit_id')
                ->filter()
                ->unique()
                ->values();

            $activeMasterUnitIds = Unit::query()
                ->whereIn('id', $unitIds)
                ->where('is_active', true)
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();

            foreach ($units as $index => $unit) {
                if (! isset($unit['unit_id'])) {
                    continue;
                }

                $isNew = empty($unit['id']);

                $productUnitIsActive = ! array_key_exists(
                    'is_active',
                    $unit
                ) || filter_var(
                    $unit['is_active'],
                    FILTER_VALIDATE_BOOLEAN
                );

                $requiresActiveMaster = $isNew
                    || $productUnitIsActive;

                if (
                    $requiresActiveMaster
                    && ! in_array(
                        (int) $unit['unit_id'],
                        $activeMasterUnitIds,
                        true
                    )
                ) {
                    $validator->errors()->add(
                        "units.{$index}.unit_id",
                        'New or active product units must use an active unit master.'
                    );
                }
            }

            return;
        }

        if (! $isActivating) {
            return;
        }

        $activeBaseUnits = $product
            ->productUnits
            ->filter(
                fn ($productUnit) => $productUnit->is_base
                    && $productUnit->is_active
            );

        if ($activeBaseUnits->count() !== 1) {
            $validator->errors()->add(
                'units',
                'Product cannot be activated without exactly one active base unit.'
            );
        }

        $hasInactiveMaster = $product
            ->productUnits
            ->filter(
                fn ($productUnit) => $productUnit->is_active
            )
            ->contains(
                fn ($productUnit) => ! $productUnit->unit?->is_active
            );

        if ($hasInactiveMaster) {
            $validator->errors()->add(
                'units',
                'Product cannot be activated while an active product unit uses an inactive unit master.'
            );
        }
    }

    private function validateBarcodes(
        Validator $validator
    ): void {
        if (! $this->has('barcodes')) {
            return;
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
                    'Unit-specific barcode assignment is not supported yet.'
                );
            }
        }
    }
}
