<?php

namespace App\Actions\Inventory;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Support\DecimalMath;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

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
                    ->where('product_id', $productId)
                    ->whereKey($validated['product_unit_id'])
                    ->where('is_active', true)
                    ->firstOrFail();

                InventoryBalance::query()->firstOrCreate(
                    ['product_id' => $productId],
                    ['quantity_base' => 0]
                );

                $balance = InventoryBalance::query()
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $type = $validated['adjustment_type'];

                try {
                    $quantityFormatted = DecimalMath::normalize(
                        (string) $validated['quantity']
                    );
                    $conversionFormatted = DecimalMath::normalize(
                        (string) $productUnit->conversion_to_base
                    );
                    $beforeFormatted = DecimalMath::normalize(
                        (string) $balance->quantity_base
                    );

                    if ($conversionFormatted === '0.000000') {
                        throw ValidationException::withMessages([
                            'quantity' => 'Product unit conversion must be greater than zero.',
                        ]);
                    }

                    $quantityBase = DecimalMath::multiply(
                        $quantityFormatted,
                        $conversionFormatted
                    );

                    $deltaFormatted = match ($type) {
                        'STOCK_OPNAME' => DecimalMath::add(
                            $quantityBase,
                            '-'.$beforeFormatted
                        ),
                        'DAMAGE', 'LOSS' => '-'.$quantityBase,
                        'ADJUSTMENT' => $quantityBase,
                        default => throw ValidationException::withMessages([
                            'adjustment_type' => 'Invalid adjustment type.',
                        ]),
                    };

                    $afterFormatted = DecimalMath::add(
                        $beforeFormatted,
                        $deltaFormatted
                    );
                } catch (DomainException|InvalidArgumentException|OverflowException $exception) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stock adjustment exceeds supported decimal precision or range.',
                    ]);
                }

                if (str_starts_with($afterFormatted, '-')) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Adjustment cannot result in negative inventory.',
                    ]);
                }

                if (
                    $type !== 'STOCK_OPNAME'
                    && $deltaFormatted === '0.000000'
                ) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Adjustment must produce a non-zero stock movement.',
                    ]);
                }

                $occurredAt = $validated['occurred_at'] ?? now();

                $adjustment = StockAdjustment::create([
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

    private function generateAdjustmentNumber(): string
    {
        return 'ADJ-'
            .now()->format('Ymd')
            .'-'
            .Str::upper((string) Str::ulid());
    }
}
