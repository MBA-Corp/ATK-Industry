<?php

namespace App\Actions\Sales;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class QuerySalesReport
{
    public function execute(array $filters): Builder
    {
        $group = ['report_date'];
        $groupBy = $filters['group_by'] ?? 'sku';
        if ($groupBy !== 'day') {
            array_push($group, 'product_id', 'product_sku');
        }
        if ($groupBy === 'unit') {
            array_push($group, 'unit_code', 'conversion_to_base');
        }

        $report = DB::query()->fromSub($this->events($filters), 'events')
            ->select($group)->groupBy(...$group)
            ->selectRaw("COUNT(DISTINCT CASE WHEN event_kind = 'sale' THEN sale_id END) as transaction_count,
                SUM(CASE WHEN event_kind = 'sale' THEN 1 ELSE 0 END) as sales_line_count,
                COUNT(DISTINCT CASE WHEN event_kind = 'cancel' THEN sale_id END) as cancelled_transaction_count,
                SUM(CASE WHEN event_kind <> 'return' THEN amount ELSE 0 END) as gross_sales,
                SUM(CASE WHEN event_kind = 'cancel' THEN amount ELSE 0 END) as cancellations,
                SUM(CASE WHEN event_kind = 'return' THEN amount ELSE 0 END) as sales_returns,
                SUM(CASE WHEN event_kind = 'sale' THEN amount WHEN event_kind = 'return' THEN -amount ELSE 0 END) as net_sales");

        if ($groupBy !== 'day') {
            $this->selectQuantities($report, 'quantity_base');
        }
        if ($groupBy === 'unit') {
            $this->selectQuantities($report, 'quantity');
        }

        $report->orderBy($filters['sort'] ?? 'report_date', $filters['direction'] ?? 'asc');
        foreach ($group as $column) {
            $report->orderBy($column);
        }

        return $report;
    }

    public function events(array $filters, bool $includeCosts = false): Builder
    {
        $start = CarbonImmutable::parse($filters['date_from'], 'Asia/Jakarta')->utc();
        $end = CarbonImmutable::parse($filters['date_to'], 'Asia/Jakarta')->addDay()->utc();
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $saleDay = $this->localDate('s.completed_at', $sqlite);
        $returnDay = $this->localDate('r.returned_at', $sqlite);
        $quantity = $this->quantity('i.quantity', $sqlite);
        $base = $this->quantity('i.quantity * i.conversion_to_base', $sqlite);
        $returnQuantity = $this->quantity('ri.quantity', $sqlite);
        $returnBase = $this->quantity('ri.quantity_base', $sqlite);

        $sales = DB::table('sales as s')
            ->join('sale_items as i', 'i.sale_id', '=', 's.id')
            ->whereIn('s.status', ['COMPLETED', 'CANCELLED'])
            ->where('s.completed_at', '>=', $start)->where('s.completed_at', '<', $end)
            ->selectRaw("$saleDay as report_date, i.product_id, i.product_sku, i.unit_code, i.conversion_to_base,
                i.sale_id, CASE WHEN s.status = 'CANCELLED' THEN 'cancel' ELSE 'sale' END as event_kind,
                CAST(i.line_total AS DECIMAL(30, 0)) as amount, $quantity as quantity, $base as quantity_base");

        $returns = DB::table('sale_returns as r')
            ->join('sale_return_items as ri', 'ri.sale_return_id', '=', 'r.id')
            ->join('sale_items as i', 'i.id', '=', 'ri.sale_item_id')
            ->where('r.returned_at', '>=', $start)->where('r.returned_at', '<', $end)
            ->selectRaw("$returnDay as report_date, i.product_id, i.product_sku, i.unit_code, i.conversion_to_base,
                i.sale_id, 'return' as event_kind, CAST(ri.refund_amount AS DECIMAL(30, 0)) as amount,
                $returnQuantity as quantity, $returnBase as quantity_base");

        if ($includeCosts) {
            $sales->addSelect('i.cost_total as cost');
            $returns->addSelect('ri.cost_reversal as cost');
        }

        foreach (['product_id', 'product_sku', 'unit_code'] as $field) {
            if (isset($filters[$field])) {
                $sales->where("i.$field", $filters[$field]);
                $returns->where("i.$field", $filters[$field]);
            }
        }

        return $sales->unionAll($returns);
    }

    private function localDate(string $column, bool $sqlite): string
    {
        return $sqlite ? "date($column, '+7 hours')" : "DATE(DATE_ADD($column, INTERVAL 7 HOUR))";
    }

    private function quantity(string $expression, bool $sqlite): string
    {
        return $sqlite
            ? "CAST(ROUND(($expression) * 1000000) AS INTEGER)"
            : "CAST(($expression) AS DECIMAL(30, 6))";
    }

    private function selectQuantities(Builder $report, string $column): void
    {
        $report->selectRaw("SUM(CASE WHEN event_kind = 'sale' THEN $column ELSE 0 END) as sold_$column,
            SUM(CASE WHEN event_kind = 'return' THEN $column ELSE 0 END) as returned_$column,
            SUM(CASE WHEN event_kind = 'cancel' THEN $column ELSE 0 END) as cancelled_$column,
            SUM(CASE WHEN event_kind = 'sale' THEN $column WHEN event_kind = 'return' THEN -$column ELSE 0 END) as net_$column");
    }
}
