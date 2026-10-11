<?php

namespace App\Actions\Purchasing;

use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OverflowException;

class CreatePurchaseAction
{
    public function execute(
        array $validated,
        ?int $createdBy = null
    ): Purchase {
        return DB::transaction(
            function () use ($validated, $createdBy) {
                $productUnits = ProductUnit::query()
                    ->whereIn(
                        'id',
                        collect($validated['items'])
                            ->pluck('product_unit_id')
                            ->unique()
                            ->values()
                    )
                    ->get(['id', 'conversion_to_base'])
                    ->keyBy('id');
                $lineTotals = [];
                $subtotal = 0;
                $operationalCost = (int) (
                    $validated['operational_cost'] ?? 0
                );
                try {
                    foreach ($validated['items'] as $index => $item) {
                        $lineTotal = MoneyMath::lineTotal(
                            (string) $item['qty_ordered'],
                            (int) $item['purchase_price']
                        );
                        $lineTotals[$index] = $lineTotal;
                        $subtotal = MoneyMath::add(
                            $subtotal,
                            $lineTotal
                        );
                    }
                    $grandTotal = MoneyMath::add(
                        $subtotal,
                        $operationalCost
                    );
                } catch (OverflowException $exception) {
                    throw ValidationException::withMessages([
                        'items' => 'Purchase total exceeds supported integer range.',
                    ]);
                }
                $purchase = Purchase::create([
                    'purchase_number' => $this->generatePurchaseNumber(),
                    'supplier_id' => $validated['supplier_id'],
                    'supplier_invoice_number' => $validated[
                        'supplier_invoice_number'
                    ] ?? null,
                    'purchase_date' => $validated['purchase_date'],
                    'status' => 'DRAFT',
                    'subtotal' => $subtotal,
                    'operational_cost' => $operationalCost,
                    'grand_total' => $grandTotal,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $createdBy,
                ]);
                foreach ($validated['items'] as $index => $item) {
                    $productUnit = $productUnits->get(
                        (int) $item['product_unit_id']
                    );
                    $purchase->items()->create([
                        'product_supplier_id' => $item['product_supplier_id'],
                        'product_unit_id' => $item['product_unit_id'],
                        'conversion_to_base' => $productUnit->conversion_to_base,
                        'qty_ordered' => $item['qty_ordered'],
                        'qty_received' => 0,
                        'purchase_price' => $item['purchase_price'],
                        'line_total' => $lineTotals[$index],
                    ]);
                }

                return $purchase->load([
                    'supplier:id,code,name',
                    'createdBy:id,name,username',
                    'items.productSupplier.product:id,sku,name',
                    'items.productUnit.unit:id,code,name',
                ]);
            },
            3
        );
    }

    private function generatePurchaseNumber(): string
    {
        return 'PUR-'
            .now()->format('Ymd')
            .'-'
            .Str::upper((string) Str::ulid());
    }
}
