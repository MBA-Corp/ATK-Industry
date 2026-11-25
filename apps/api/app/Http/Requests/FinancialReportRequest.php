<?php

namespace App\Http\Requests;

class FinancialReportRequest extends ReportDateRangeRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'hpp_method' => ['sometimes', 'in:periodic,perpetual'],
            'product_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'between:1,10000'],
        ];
    }
}
