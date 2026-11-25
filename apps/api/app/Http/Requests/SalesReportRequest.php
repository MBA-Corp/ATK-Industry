<?php

namespace App\Http\Requests;

class SalesReportRequest extends ReportDateRangeRequest
{
    public function rules(): array
    {
        return parent::rules() + [
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
}
