<?php

namespace App\Actions\Inventory;

use App\Models\StockMovement;
use App\Support\CostMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInventoryValuation
{
    // Called only inside ApplyStockMovementAction's transaction/product lock.
    public function execute(StockMovement $movement, ?string $incomingValue = null): void
    {
        $row = DB::table('inventory_costs')->where('product_id', $movement->product_id)->lockForUpdate()->first();
        $before = bccomp($movement->quantity_before_base, '0', 6) === 0
            ? '0.000000' : ($row?->inventory_value);
        $delta = null;
        $direction = bccomp($movement->quantity_delta_base, '0', 6);

        if ($incomingValue !== null) {
            $incomingValue = CostMath::round($incomingValue);
            if ($direction < 0 || bccomp($incomingValue, '0', 6) < 0
                || ($direction === 0 && bccomp($incomingValue, '0', 6) !== 0)) {
                throw ValidationException::withMessages(['cost' => 'Acquisition value requires non-negative incoming quantity and value.']);
            }
        }

        if ($direction === 0) {
            $delta = '0.000000';
        } elseif ($direction > 0) {
            // Unknown acquisition cost makes the blended inventory cost unknown.
            $delta = $incomingValue;
        } elseif ($before !== null) {
            $delta = '-'.CostMath::proportional(
                (string) $before,
                substr($movement->quantity_delta_base, 1),
                $movement->quantity_before_base,
            );
        }

        $after = $before !== null && $delta !== null
            ? CostMath::round(bcadd((string) $before, $delta, 6)) : null;
        if (bccomp($movement->quantity_after_base, '0', 6) === 0) {
            $after = '0.000000';
        }
        $recordedAt = now();
        DB::table('inventory_costs')->upsert([[
            'product_id' => $movement->product_id,
            'inventory_value' => $after,
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
        ]], ['product_id'], ['inventory_value', 'updated_at']);
        DB::table('inventory_valuations')->insert([
            'stock_movement_id' => $movement->id,
            'value_before' => $before,
            'value_delta' => $delta,
            'value_after' => $after,
            'policy' => 'moving_average_excluding_freight_provisional',
            'created_at' => now(),
        ]);
        if ($movement->source_type === 'SALE_ITEM') {
            DB::table('sale_items')->where('id', $movement->source_id)->update([
                'cost_total' => $delta === null ? null : ltrim($delta, '-'),
            ]);
        }
    }
}
