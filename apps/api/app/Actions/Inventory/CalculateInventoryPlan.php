<?php

namespace App\Actions\Inventory;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class CalculateInventoryPlan
{
    private const SCALE = 18;

    public function execute(object $product, array $dailySales, CarbonImmutable $today): array
    {
        $stock = bcadd((string) $product->stock, '0', 6);
        $configured = $product->lead_time_days !== null && $product->base_unit_code !== null;
        $min = $product->minimum_stock === null ? null : bcadd((string) $product->minimum_stock, '0', 6);
        $max = $product->maximum_stock === null ? null : bcadd((string) $product->maximum_stock, '0', 6);
        $observations = [];
        $average = $deviation = $safety = $reorder = null;
        $method = 'insufficient_data';

        if ($configured) {
            $start = CarbonImmutable::parse($product->history_from, 'Asia/Jakarta')
                ->max($today->subDays($product->demand_window_days));
            if ($start->lessThan($today)) {
                foreach (CarbonPeriod::create($start, $today->subDay()) as $day) {
                    $date = $day->toDateString();
                    $observations[$date] = $dailySales[$date] ?? '0.000000';
                }
            }

            if (count($observations) >= $product->minimum_history_days) {
                [$average, $deviation] = $this->statistics($observations);
                $lead = (string) $product->lead_time_days;
                $rawSafety = bcmul(bcmul('1.645', $deviation, self::SCALE), bcsqrt($lead, self::SCALE), self::SCALE);
                $rawReorder = bcadd(bcmul($average, $lead, self::SCALE), $rawSafety, self::SCALE);
                $step = (string) $product->quantity_step;
                $safety = $this->ceilToStep($rawSafety, $step);
                $reorder = $this->ceilToStep($rawReorder, $step);
                $method = 'statistical';
            } elseif ($min !== null) {
                $reorder = $min;
                $method = 'manual_minimum';
            }
        }

        $reorderThreshold = $reorder === null || ($min !== null && bccomp($min, $reorder, 6) > 0)
            ? $min
            : $reorder;

        return [
            'product_id' => (int) $product->id,
            'sku' => $product->sku,
            'base_unit_code' => $product->base_unit_code,
            'stock_base' => $stock,
            'minimum_stock_base' => $min,
            'maximum_stock_base' => $max,
            'overstock' => $max !== null && bccomp($stock, $max, 6) > 0,
            'lead_time_days' => $configured ? (int) $product->lead_time_days : null,
            'history_from' => $configured ? $product->history_from : null,
            'demand_window_days' => $configured ? (int) $product->demand_window_days : null,
            'minimum_history_days' => $configured ? (int) $product->minimum_history_days : null,
            'quantity_step_base' => $configured ? bcadd((string) $product->quantity_step, '0', 6) : null,
            'service_level' => '0.95',
            'z_score' => '1.645',
            'observed_days' => count($observations),
            'average_daily_demand_base' => $average === null ? null : bcadd($average, '0', 6),
            'standard_deviation_daily_demand_base' => $deviation === null ? null : bcadd($deviation, '0', 6),
            'safety_stock_base' => $safety,
            'reorder_point_base' => $reorder,
            'reorder_threshold_base' => $reorderThreshold,
            'method' => $method,
            'stock_status' => match (true) {
                $product->base_unit_code === null => 'insufficient_data',
                bccomp($stock, '0', 6) <= 0 => 'out_of_stock',
                $reorderThreshold === null => 'insufficient_data',
                $safety !== null && bccomp($stock, $safety, 6) <= 0 => 'critical',
                bccomp($stock, $reorderThreshold, 6) <= 0 => 'reorder_needed',
                default => 'sufficient',
            },
            'provisional' => true,
            'daily_demand' => $observations,
        ];
    }

    private function statistics(array $observations): array
    {
        $sum = '0';
        foreach ($observations as $quantity) {
            $sum = bcadd($sum, $quantity, self::SCALE);
        }
        $days = (string) count($observations);
        $average = bcdiv($sum, $days, self::SCALE);
        $squaredDifferences = '0';
        foreach ($observations as $quantity) {
            $difference = bcsub($quantity, $average, self::SCALE);
            $squaredDifferences = bcadd($squaredDifferences, bcmul($difference, $difference, self::SCALE), self::SCALE);
        }

        return [$average, bcsqrt(bcdiv($squaredDifferences, $days, self::SCALE), self::SCALE)];
    }

    private function ceilToStep(string $value, string $step): string
    {
        $units = bcdiv($value, $step, 0);
        if (bccomp(bcmul($units, $step, self::SCALE), $value, self::SCALE) < 0) {
            $units = bcadd($units, '1', 0);
        }

        return bcmul($units, $step, 6);
    }
}
