<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Support\CostMath;
use App\Support\DecimalMath;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnSaleAction
{
    public function execute(int $saleId, array $data, int $actor): int
    {
        $hash = hash('sha256', json_encode([$saleId, $actor, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($saleId, $data, $actor, $hash) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($saleId);
            $existing = DB::table('sale_returns')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Return idempotency key conflicts.');

                return $existing->id;
            }
            if ($sale->status !== 'COMPLETED' || $sale->cancellation()->exists()) {
                throw ValidationException::withMessages(['sale' => 'Only completed, non-cancelled sales may be returned.']);
            }
            $replacementId = $data['replacement_sale_id'] ?? null;
            if ($replacementId !== null && ((int) $replacementId === $saleId
                || ! Sale::whereKey($replacementId)->where('status', 'COMPLETED')->exists())) {
                throw ValidationException::withMessages(['replacement_sale_id' => 'Replacement must be a different completed sale.']);
            }
            $items = $sale->items()->whereIn('id', array_column($data['items'], 'sale_item_id'))
                ->orderBy('product_id')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== count($data['items'])) {
                throw ValidationException::withMessages(['items' => 'Every distinct item must belong to this sale.']);
            }
            $input = collect($data['items'])->keyBy('sale_item_id');
            $returnId = DB::table('sale_returns')->insertGetId([
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
                'sale_id' => $saleId, 'actor_id' => $actor, 'replacement_sale_id' => $replacementId,
                'reason' => $data['reason'], 'refund_due' => 0, 'returned_at' => now(),
            ]);
            $refundDue = 0;
            foreach ($items as $item) {
                $line = $input[$item->id];
                $previous = DB::table('sale_return_items')->where('sale_item_id', $item->id)->get();
                $returnedQty = '0';
                $returnedBase = '0';
                $allocatedCost = '0';
                $refunded = 0;
                foreach ($previous as $prior) {
                    $returnedQty = bcadd($returnedQty, (string) $prior->quantity, 6);
                    $returnedBase = bcadd($returnedBase, (string) $prior->quantity_base, 6);
                    $allocatedCost = bcadd($allocatedCost, (string) ($prior->allocated_cost ?? '0'), 6);
                    $refunded = MoneyMath::add($refunded, (int) $prior->refund_amount);
                }
                $quantity = DecimalMath::normalize((string) $line['quantity']);
                $cumulative = bcadd($returnedQty, $quantity, 6);
                if (bccomp($quantity, '0', 6) <= 0 || bccomp($cumulative, $item->quantity, 6) > 0) {
                    throw ValidationException::withMessages(['items' => 'Return exceeds the remaining sold quantity.']);
                }
                $originalMovement = StockMovement::where('source_type', 'SALE_ITEM')->where('source_id', $item->id)->first();
                if ($originalMovement !== null && ((int) $originalMovement->product_id !== (int) $item->product_id
                    || bccomp($originalMovement->quantity_delta_base, '0', 6) >= 0)) {
                    throw ValidationException::withMessages(['items' => 'Original sale ledger is inconsistent.']);
                }
                $originalBase = $originalMovement !== null
                    ? substr($originalMovement->quantity_delta_base, 1)
                    : DecimalMath::multiply($item->quantity, $item->conversion_to_base);
                $base = bcsub(CostMath::proportional($originalBase, $cumulative, $item->quantity), $returnedBase, 6);
                $exactBase = bcdiv(bcmul($originalBase, $quantity, 24), $item->quantity, 24);
                if ($line['condition'] === 'resellable' && (bccomp($base, '0', 6) <= 0 || bccomp($exactBase, $base, 24) !== 0)) {
                    throw ValidationException::withMessages(['items' => 'Restock quantity must be representable in base-unit precision; return a larger quantity.']);
                }
                $refund = (int) CostMath::proportional((string) $item->line_total, $cumulative, $item->quantity, 0) - $refunded;
                $cost = $item->cost_total === null ? null
                    : bcsub(CostMath::proportional((string) $item->cost_total, $cumulative, $item->quantity), $allocatedCost, 6);
                $restock = $line['condition'] === 'resellable';
                $rowId = DB::table('sale_return_items')->insertGetId([
                    'sale_return_id' => $returnId, 'sale_item_id' => $item->id,
                    'quantity' => $quantity, 'quantity_base' => $base, 'condition' => $line['condition'],
                    'refund_amount' => $refund, 'allocated_cost' => $cost,
                    'cost_reversal' => $restock ? $cost : '0.000000',
                ]);
                if ($restock && bccomp($base, '0', 6) > 0) {
                    app(ApplyStockMovementAction::class)->execute(
                        $item->product_id, 'SALE_RETURN', 'SALE_RETURN_ITEM', $rowId, $base,
                        productUnitId: $item->product_unit_id, sourceQuantity: $quantity,
                        conversionToBase: $item->conversion_to_base, actorUserId: $actor,
                        terminalId: $sale->terminal_id, reason: $data['reason'], incomingValue: $cost,
                    );
                }
                $refundDue = MoneyMath::add($refundDue, $refund);
            }
            DB::table('sale_returns')->where('id', $returnId)->update(['refund_due' => $refundDue]);

            return $returnId;
        }, 3);
    }
}
