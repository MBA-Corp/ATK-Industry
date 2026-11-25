<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InventoryPlanningPolicyRequest extends FormRequest
{
    public function rules(): array
    {
        $quantity = ['regex:/\A\d{1,12}(?:\.\d{1,6})?\z/'];

        return [
            'history_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::today('Asia/Jakarta')->toDateString()],
            'lead_time_days' => ['required', 'integer', 'between:1,365'],
            'demand_window_days' => ['required', 'integer', 'between:2,366'],
            'minimum_history_days' => ['required', 'integer', 'between:2,366', 'lte:demand_window_days'],
            'quantity_step' => ['required', ...$quantity],
            'minimum_stock' => ['present', 'nullable', ...$quantity],
            'maximum_stock' => ['present', 'nullable', ...$quantity],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $data = $validator->validated();
            if (bccomp((string) $data['quantity_step'], '0', 6) <= 0) {
                $validator->errors()->add('quantity_step', 'Quantity step must be positive.');
            }
            if ($data['minimum_stock'] !== null && $data['maximum_stock'] !== null
                && bccomp((string) $data['minimum_stock'], (string) $data['maximum_stock'], 6) > 0) {
                $validator->errors()->add('maximum_stock', 'Maximum stock must be greater than or equal to minimum stock.');
            }
        }];
    }
}
