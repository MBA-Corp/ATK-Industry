<?php

namespace App\Actions\Reporting;

use App\Actions\Sales\QuerySalesReport;
use App\Models\PurchaseReceiptItem;
use App\Support\MoneyMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportFinancials
{
    public function __construct(private QuerySalesReport $sales) {}

    public function profitAndLoss(array $filters): array
    {
        return DB::transaction(function () use ($filters): array {
            $start = CarbonImmutable::parse($filters['date_from'], 'Asia/Jakarta')->utc();
            $end = CarbonImmutable::parse($filters['date_to'], 'Asia/Jakarta')->addDay()->utc();
            $sales = $this->margins(array_intersect_key($filters, array_flip(['date_from', 'date_to'])))->first();
            $opening = $this->inventoryValueAt($start);
            $closing = $this->inventoryValueAt($end);
            $grossPurchases = $this->receivedPurchases($start, $end);
            $unclassified = (string) DB::table('purchases')->where('status', 'RECEIVED')
                ->where('received_at', '>=', $start)->where('received_at', '<', $end)->sum('operational_cost');
            $expenses = (string) DB::table('operating_expenses')->whereNull('voided_at')
                ->whereBetween('expense_date', [$filters['date_from'], $filters['date_to']])->sum('amount');
            $otherMovements = DB::table('stock_movements')->where('occurred_at', '>=', $start)
                ->where('occurred_at', '<', $end)
                ->whereNotIn('movement_type', ['PURCHASE_RECEIPT', 'SALE', 'SALE_RETURN', 'SALE_REVERSAL'])
                ->count();

            $amounts = ProfitAmounts::summarize(
                (string) $sales->gross_sales,
                (string) $sales->sales_returns,
                '0',
                $grossPurchases,
                $unclassified === '0' ? '0' : null,
                '0',
                '0',
                $opening,
                $closing,
                $sales->missing_cost_lines > 0 ? null : $this->decimal($sales->snapshot_hpp),
                $expenses,
                $filters['hpp_method'] ?? 'perpetual',
            );

            return $amounts + [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'provisional' => true,
                'reconciliation_status' => $amounts['hpp'] === null ? 'incomplete' : 'requires_review',
                'missing_cost_lines' => (int) $sales->missing_cost_lines,
                'unclassified_purchase_cost' => $unclassified,
                'non_sales_or_purchase_movements' => $otherMovements,
                'purchase_adjustments_supported' => false,
                'sales_discounts_tracked_separately' => false,
                'valuation_policy' => 'moving_average_excluding_freight_provisional',
            ];
        });
    }

    public function margins(array $filters, ?string $group = null): Builder
    {
        $cost = $this->costExpression('cost');
        $query = DB::query()->fromSub($this->sales->events($filters, includeCosts: true), 'events')
            ->where('event_kind', '<>', 'cancel')
            ->selectRaw("COALESCE(SUM(CASE WHEN event_kind = 'sale' THEN amount ELSE 0 END), 0) as gross_sales,
                COALESCE(SUM(CASE WHEN event_kind = 'return' THEN amount ELSE 0 END), 0) as sales_returns,
                COALESCE(SUM(CASE WHEN event_kind = 'sale' THEN amount ELSE -amount END), 0) as net_sales,
                COALESCE(SUM(CASE WHEN event_kind = 'sale' THEN $cost ELSE -$cost END), 0) as snapshot_hpp,
                COALESCE(SUM(CASE WHEN cost IS NULL THEN 1 ELSE 0 END), 0) as missing_cost_lines");

        return match ($group) {
            'product' => $query->addSelect('product_id', 'product_sku')->groupBy('product_id', 'product_sku')
                ->orderBy('product_id')->orderBy('product_sku'),
            'transaction' => $query->addSelect('sale_id')->groupBy('sale_id')->orderBy('sale_id'),
            default => $query,
        };
    }

    public function formatMargin(object $row): array
    {
        $hpp = $row->missing_cost_lines > 0 ? null : $this->decimal($row->snapshot_hpp);
        $net = (string) $row->net_sales;
        $gross = $hpp === null ? null : bcsub($net, $hpp, 6);

        return array_filter([
            'product_id' => $row->product_id ?? null,
            'product_sku' => $row->product_sku ?? null,
            'sale_id' => $row->sale_id ?? null,
        ], static fn ($value) => $value !== null) + [
            'gross_sales' => (string) $row->gross_sales,
            'sales_returns' => (string) $row->sales_returns,
            'net_sales' => $net,
            'hpp' => $hpp,
            'gross_profit' => $gross,
            'margin_percentage' => ProfitAmounts::margin($net, $gross),
            'missing_cost_lines' => (int) $row->missing_cost_lines,
            'provisional' => true,
        ];
    }

    private function inventoryValueAt(CarbonImmutable $cutoff): ?string
    {
        $sequence = DB::table('stock_movements')->groupBy('product_id')
            ->selectRaw('product_id, MAX(CASE WHEN occurred_at < ? THEN id END) as last_included,
                MIN(CASE WHEN occurred_at >= ? THEN id END) as first_excluded', [$cutoff, $cutoff]);
        $value = $this->costExpression('v.value_after');
        $balances = DB::query()->fromSub($sequence, 'positions')
            ->join('stock_movements as m', 'm.id', '=', 'positions.last_included')
            ->leftJoin('inventory_valuations as v', 'v.stock_movement_id', '=', 'm.id')
            ->selectRaw("COALESCE(SUM(CASE WHEN m.quantity_after_base = 0 THEN 0 ELSE $value END), 0) as inventory_value,
                COALESCE(SUM(CASE WHEN positions.first_excluded < positions.last_included
                    OR (m.quantity_after_base <> 0 AND v.value_after IS NULL) THEN 1 ELSE 0 END), 0) as unavailable")
            ->first();

        return $balances->unavailable > 0 ? null : $this->decimal($balances->inventory_value);
    }

    private function receivedPurchases(CarbonImmutable $start, CarbonImmutable $end): string
    {
        $receipts = PurchaseReceiptItem::query()
            ->join('purchase_receipts as r', 'r.id', '=', 'purchase_receipt_items.purchase_receipt_id')
            ->join('purchases as p', 'p.id', '=', 'r.purchase_id')
            ->join('purchase_items as i', 'i.id', '=', 'purchase_receipt_items.purchase_item_id')
            ->where('p.status', 'RECEIVED')->where('r.received_at', '>=', $start)->where('r.received_at', '<', $end)
            ->select('purchase_receipt_items.id', 'purchase_receipt_items.qty_received', 'i.purchase_price')
            ->lazyById(500, 'purchase_receipt_items.id', 'id');

        $total = '0';
        foreach ($receipts as $line) {
            $total = bcadd($total, (string) MoneyMath::lineTotal($line->qty_received, (int) $line->purchase_price), 0);
        }

        return $total;
    }

    private function costExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(ROUND($column * 1000000) AS INTEGER)"
            : $column;
    }

    private function decimal(int|string $amount): string
    {
        return bcdiv((string) $amount, DB::connection()->getDriverName() === 'sqlite' ? '1000000' : '1', 6);
    }
}
