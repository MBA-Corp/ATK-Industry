<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sales\QuerySalesReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    private const EXPORT_LIMIT = 10000;

    public function index(SalesReportRequest $request, QuerySalesReport $report): JsonResponse
    {
        $page = $report->execute($request->validated())
            ->paginate($request->integer('per_page', 25))
            ->appends($request->validated())
            ->through($this->formatRow(...));

        return response()->json($page)->header('Cache-Control', 'private, no-store');
    }

    public function export(SalesReportRequest $request, QuerySalesReport $report): StreamedResponse
    {
        $filters = $request->validated();
        $rows = $report->execute($filters)->limit(self::EXPORT_LIMIT + 1)->get();
        if ($rows->count() > self::EXPORT_LIMIT) {
            throw ValidationException::withMessages(['date_to' => 'Export exceeds 10000 rows. Narrow the report filters.']);
        }

        $group = $filters['group_by'] ?? 'sku';
        $headings = ['report_date'];
        if ($group !== 'day') {
            array_push($headings, 'product_id', 'product_sku');
        }
        if ($group === 'unit') {
            array_push($headings, 'unit_code', 'conversion_to_base');
        }
        array_push($headings, 'transaction_count', 'sales_line_count', 'cancelled_transaction_count', 'gross_sales', 'cancellations', 'sales_returns', 'net_sales');
        foreach (['sold', 'returned', 'cancelled', 'net'] as $kind) {
            if ($group !== 'day') {
                $headings[] = $kind.'_quantity_base';
            }
            if ($group === 'unit') {
                $headings[] = $kind.'_quantity';
            }
        }

        return response()->streamDownload(function () use ($headings, $rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headings, escape: '');
            foreach ($rows as $record) {
                $row = $this->formatRow($record);
                foreach (['product_sku', 'unit_code'] as $textField) {
                    if (isset($row[$textField]) && preg_match('/^[\s\p{C}]*[=+\-@]|^[\t\r\n]/u', $row[$textField])) {
                        $row[$textField] = "'".$row[$textField];
                    }
                }
                fputcsv($output, array_map(fn (string $key) => $row[$key], $headings), escape: '');
            }
            fclose($output);
        }, 'sales-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function formatRow(object $record): array
    {
        $row = (array) $record;
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        foreach ($row as $field => $value) {
            if (str_ends_with($field, '_quantity') || str_ends_with($field, '_quantity_base')) {
                $row[$field] = bcdiv((string) $value, $sqlite ? '1000000' : '1', 6);
            } elseif ($field === 'conversion_to_base') {
                $row[$field] = bcadd((string) $value, '0', 6);
            } elseif (in_array($field, ['gross_sales', 'cancellations', 'sales_returns', 'net_sales'], true)) {
                $row[$field] = (string) $value;
            } elseif (str_ends_with($field, '_count') || $field === 'product_id') {
                $row[$field] = (int) $value;
            }
        }

        return $row;
    }
}
