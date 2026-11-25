<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportDateRangeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
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
