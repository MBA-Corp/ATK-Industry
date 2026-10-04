<?php

namespace App\Actions\Inventory;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateStockAdjustmentAction
{
    public function __construct(
        private ApplyStockMovementAction $applyStockMovement
    ) {}

    public function execute(
        int $productId,
        array $validated,
        ?int $createdBy = null
    ): StockAdjustment {
        return DB::transaction(
            function () use (
                $productId,
                $validated,
                $createdBy
            ) {
                Product::query()
                    ->lockForUpdate()
                    ->findOrFail($productId);

                $productUnit = ProductUnit::query()
                    ->where(
                        'product_id',
                        $productId
                    )
                    ->whereKey(
                        $validated['product_unit_id']
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->firstOrFail();

                InventoryBalance::query()
                    ->firstOrCreate(
                        [
                            'product_id' => $productId,
                        ],
                        [
                            'quantity_base' => 0,
                        ]
                    );

                $balance = InventoryBalance::query()
                    ->where(
                        'product_id',
                        $productId
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $quantityBefore =
                    (float) $balance->quantity_base;

                $quantity =
                    (float) $validated['quantity'];

                $conversion =
                    (float) $productUnit
                        ->conversion_to_base;

                $type =
                    $validated['adjustment_type'];

                $quantityDelta =
                    $this->calculateDelta(
                        $type,
                        $quantity,
                        $conversion,
                        $quantityBefore
                    );

                $quantityAfter =
                    $quantityBefore
                    + $quantityDelta;

                if ($quantityAfter < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Adjustment cannot result in negative inventory.',
                    ]);
                }

                $quantityFormatted = number_format(
                    $quantity,
                    6,
                    '.',
                    ''
                );

                $conversionFormatted = number_format(
                    $conversion,
                    6,
                    '.',
                    ''
                );

                $deltaFormatted = number_format(
                    $quantityDelta,
                    6,
                    '.',
                    ''
                );

                $beforeFormatted = number_format(
                    $quantityBefore,
                    6,
                    '.',
                    ''
                );

                $afterFormatted = number_format(
                    $quantityAfter,
                    6,
                    '.',
                    ''
                );

                $occurredAt =
                    $validated['occurred_at']
                    ?? now();

                $adjustment =
                    StockAdjustment::create([
                        'adjustment_number' => $this->generateAdjustmentNumber(),

                        'product_id' => $productId,

                        'product_unit_id' => $productUnit->id,

                        'adjustment_type' => $type,

                        'quantity' => $quantityFormatted,

                        'conversion_to_base' => $conversionFormatted,

                        'quantity_delta_base' => $deltaFormatted,

                        'quantity_before_base' => $beforeFormatted,

                        'quantity_after_base' => $afterFormatted,

                        'reason' => $validated['reason'],

                        'created_by' => $createdBy,

                        'occurred_at' => $occurredAt,
                    ]);

                $this->applyStockMovement->execute(
                    productId: $productId,

                    movementType: $type,

                    sourceType: 'STOCK_ADJUSTMENT',

                    sourceId: $adjustment->id,

                    quantityDeltaBase: $deltaFormatted,

                    productUnitId: $productUnit->id,

                    sourceQuantity: $quantityFormatted,

                    conversionToBase: $conversionFormatted,

                    actorUserId: $createdBy,

                    reason: $validated['reason'],

                    occurredAt: $occurredAt
                );

                return $adjustment->load([
                    'product:id,sku,name',
                    'productUnit.unit:id,code,name',
                    'createdBy:id,name,username',
                ]);
            },
            3
        );
    }

    private function calculateDelta(
        string $type,
        float $quantity,
        float $conversion,
        float $quantityBefore
    ): float {
        $quantityBase =
            $quantity * $conversion;

        return match ($type) {
            'STOCK_OPNAME' => $quantityBase
                - $quantityBefore,

            'DAMAGE',
            'LOSS' => -$quantityBase,

            'ADJUSTMENT' => $quantityBase,

            default => throw ValidationException::withMessages([
                'adjustment_type' => 'Invalid adjustment type.',
            ]),
        };
    }

    private function generateAdjustmentNumber(): string
    {
        return 'ADJ-'
            .now()->format('Ymd')
            .'-'
            .Str::upper(
                (string) Str::ulid()
            );
    }
}
