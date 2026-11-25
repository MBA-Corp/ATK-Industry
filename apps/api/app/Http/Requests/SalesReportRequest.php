<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SalesReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'product_id' => ['sometimes', 'integer', 'min:1'],
            'product_sku' => ['sometimes', 'string', 'max:255'],
            'unit_code' => ['sometimes', 'string', 'max:255'],
            'group_by' => ['sometimes', 'in:day,sku,unit'],
            'sort' => ['sometimes', 'in:report_date,net_sales,gross_sales,transaction_count,sales_line_count'],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'between:1,10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['date_from', 'date_to'])) {
                return;
            }

            $start = CarbonImmutable::parse($this->input('date_from'), 'Asia/Jakarta');
            $end = CarbonImmutable::parse($this->input('date_to'), 'Asia/Jakarta');
            if ($end->greaterThan($start->addDays(365))) {
                $validator->errors()->add('date_to', 'The report range cannot exceed 366 calendar days.');
            }
        }];
    }
}
