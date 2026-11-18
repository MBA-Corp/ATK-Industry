<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => 'required|uuid',
            'reason' => 'required|string|max:255',
            'replacement_sale_id' => 'nullable|integer|exists:sales,id',
            'items' => 'required|array|min:1|max:200',
            'items.*.sale_item_id' => 'required|integer|distinct',
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'regex:/\A\d{1,12}(?:\.\d{1,6})?\z/'],
            'items.*.condition' => 'required|in:resellable,damaged',
        ];
    }
}
