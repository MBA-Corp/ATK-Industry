<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'address' => [
                'required',
                'string',
                'max:2000',
            ],

            'phone' => [
                'required',
                'string',
                'max:50',
            ],

            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                Rule::prohibitedIf(
                    $this->boolean('remove_logo')
                ),
            ],

            'remove_logo' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
