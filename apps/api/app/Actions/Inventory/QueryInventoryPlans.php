<?php

namespace App\Actions\Inventory;

use App\Actions\Sales\QuerySalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueryInventoryPlans
{
    public function __construct(
        private QuerySalesReport $sales,
        private CalculateInventoryPlan $calculate,
    ) {}

    public function products(): Builder
    {
        $baseCode = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')
            ->whereColumn('pu.product_id', 'p.id')->where('pu.is_base', true)
            ->where('pu.is_active', true)->where('u.is_active', true)
            ->where('pu.conversion_to_base', 1)->orderBy('pu.id')->select('u.code')->limit(1);

        $products = DB::table('products as p')
            ->leftJoin('inventory_balances as b', 'b.product_id', '=', 'p.id')
            ->leftJoin('inventory_planning_policies as ip', 'ip.product_id', '=', 'p.id')
            ->where('p.is_active', true)
            ->select('p.id', 'p.sku', 'p.created_at', 'ip.lead_time_days', 'ip.history_from',
                'ip.demand_window_days', 'ip.minimum_history_days')

            ->selectSub($baseCode, 'base_unit_code')
            ->orderBy('p.id');

        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        foreach ([
            'stock' => 'COALESCE(b.quantity_base, 0)',
            'quantity_step' => 'ip.quantity_step',
            'minimum_stock' => 'ip.minimum_stock',
            'maximum_stock' => 'ip.maximum_stock',
        ] as $alias => $column) {
            $expression = $sqlite ? "CASE WHEN $column IS NOT NULL THEN printf('%.6f', $column) END" : $column;
            $products->selectRaw("$expression as $alias");
        }

        return $products;
    }

    public function calculate(Collection $products): Collection
    {
        $today = CarbonImmutable::today('Asia/Jakarta');
        $configured = $products->whereNotNull('lead_time_days')->whereNotNull('base_unit_code');
        $eventsByProduct = collect();
        if ($configured->isNotEmpty()) {
            $filters = [
                'date_from' => $today->subDays($configured->max('demand_window_days'))->toDateString(),
                'date_to' => $today->subDay()->toDateString(),
            ];
            $sales = DB::query()->fromSub($this->sales->events($filters), 'events')
                ->whereIn('product_id', $configured->pluck('id'))->where('event_kind', 'sale')
                ->groupBy('product_id', 'report_date')
                ->select('product_id', 'report_date')->selectRaw('SUM(quantity_base) as demand')
                ->get();
            $eventsByProduct = $sales->groupBy('product_id');
        }

        $divisor = DB::connection()->getDriverName() === 'sqlite' ? '1000000' : '1';

        return $products->map(function (object $product) use ($eventsByProduct, $today, $divisor): array {
            $dailySales = $eventsByProduct->get($product->id, collect())
                ->mapWithKeys(fn (object $day) => [$day->report_date => bcdiv((string) $day->demand, $divisor, 6)])
                ->all();

            return $this->calculate->execute($product, $dailySales, $today);
        });
    }
}
