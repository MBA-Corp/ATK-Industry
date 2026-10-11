<?php

namespace App\Http\Requests;

use App\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReceivePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_at' => [
                'sometimes',
                'date',
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
            'items.*.purchase_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:purchase_items,id',
            ],
            'items.*.qty_received' => [
                'required',
                'numeric',
                'min:0',
                'regex:/\A\d{1,12}(?:\.\d{1,6})?\z/',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $purchase = Purchase::query()
                    ->find($this->route('purchaseId'));
                if ($purchase === null) {
                    return;
                }
                $expectedIds = $purchase->items()
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();
                $providedIds = collect($this->input('items', []))
                    ->pluck('purchase_item_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();
                if ($expectedIds !== $providedIds) {
                    $validator->errors()->add(
                        'items',
                        'Receiving must include every item from the purchase.'
                    );
                }
            },
        ];
    }
}
