<?php

namespace App\Http\Requests;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unitId');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique(
                    'units',
                    'code'
                )->ignore($unitId),
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(
                    trim((string) $this->input('code'))
                ),
            ]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (
                    ! $this->has('is_active')
                    || $this->boolean('is_active')
                ) {
                    return;
                }

                $unit = Unit::query()
                    ->find($this->route('unitId'));

                if ($unit === null) {
                    return;
                }

                $usedByActiveProduct = $unit
                    ->productUnits()
                    ->where('is_active', true)
                    ->whereHas(
                        'product',
                        fn ($query) => $query->where(
                            'is_active',
                            true
                        )
                    )
                    ->exists();

                if ($usedByActiveProduct) {
                    $validator->errors()->add(
                        'is_active',
                        'Unit cannot be deactivated while it is used by an active product unit.'
                    );
                }
            },
        ];
    }
}
