<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('categoryId');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'categories',
                    'name'
                )->ignore($categoryId),
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
                    ! $this->has('is_active')
                    || $this->boolean('is_active')
                ) {
                    return;
                }

                $category = Category::query()
                    ->find($this->route('categoryId'));

                if ($category === null) {
                    return;
                }

                $usedByActiveProduct = $category
                    ->products()
                    ->where('is_active', true)
                    ->exists();

                if ($usedByActiveProduct) {
                    $validator->errors()->add(
                        'is_active',
                        'Category cannot be deactivated while it is used by an active product.'
                    );
                }
            },
        ];
    }
}
