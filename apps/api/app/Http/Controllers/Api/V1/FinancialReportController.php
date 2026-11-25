<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reporting\ReportFinancials;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialReportRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function show(FinancialReportRequest $request, ReportFinancials $report): JsonResponse
    {
        return response()->json(['data' => $report->profitAndLoss($request->validated())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function products(FinancialReportRequest $request, ReportFinancials $report): JsonResponse
    {
        return $this->margins($request, $report, 'product');
    }

    public function transactions(FinancialReportRequest $request, ReportFinancials $report): JsonResponse
    {
        return $this->margins($request, $report, 'transaction');
    }

    public function export(FinancialReportRequest $request, ReportFinancials $report): StreamedResponse
    {
        $data = $report->profitAndLoss($request->validated());

        return response()->streamDownload(function () use ($data): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($data), escape: '');
            fputcsv($output, $data, escape: '');
            fclose($output);
        }, 'profit-loss.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function margins(FinancialReportRequest $request, ReportFinancials $report, string $group): JsonResponse
    {
        $page = $report->margins($request->validated(), $group)
            ->paginate($request->integer('per_page', 25))->appends($request->validated())
            ->through($report->formatMargin(...));

        return response()->json($page)->header('Cache-Control', 'private, no-store');
    }
}
