<?php

namespace App\Actions\Inventory;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

class ApplyStockMovementAction
{
    public function execute(
        int $productId,
        string $movementType,
        string $sourceType,
        int $sourceId,
        int|float|string $quantityDeltaBase,
        ?int $productUnitId = null,
        int|float|string|null $sourceQuantity = null,
        int|float|string|null $conversionToBase = null,
        ?int $actorUserId = null,
        ?int $terminalId = null,
        ?string $reason = null,
        mixed $occurredAt = null
    ): StockMovement {
        return DB::transaction(
            function () use (
                $productId,
                $movementType,
                $sourceType,
                $sourceId,
                $quantityDeltaBase,
                $productUnitId,
                $sourceQuantity,
                $conversionToBase,
                $actorUserId,
                $terminalId,
                $reason,
                $occurredAt
            ) {
                Product::query()
                    ->lockForUpdate()
                    ->findOrFail($productId);

                $duplicateExists =
                    StockMovement::query()
                        ->where(
                            'source_type',
                            $sourceType
                        )
                        ->where(
                            'source_id',
                            $sourceId
                        )
                        ->exists();

                if ($duplicateExists) {
                    throw ValidationException::withMessages([
                        'source' => 'Stock movement source has already been processed.',
                    ]);
                }

                InventoryBalance::query()
                    ->firstOrCreate(
                        [
                            'product_id' => $productId,
                        ],
                        [
                            'quantity_base' => 0,
                        ]
                    );

                $balance =
                    InventoryBalance::query()
                        ->where(
                            'product_id',
                            $productId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                try {
                    $quantityBeforeFormatted = DecimalMath::normalize(
                        (string) $balance->quantity_base
                    );

                    $deltaFormatted = DecimalMath::normalize(
                        (string) $quantityDeltaBase
                    );

                    $quantityAfterFormatted = DecimalMath::add(
                        $quantityBeforeFormatted,
                        $deltaFormatted
                    );
                } catch (InvalidArgumentException|OverflowException $exception) {
                    throw ValidationException::withMessages([
                        'quantity_delta_base' => 'Stock movement exceeds supported decimal precision or range.',
                    ]);
                }

                if (str_starts_with($quantityAfterFormatted, '-')) {
                    throw ValidationException::withMessages([
                        'quantity_delta_base' => 'Stock movement cannot result in negative inventory.',
                    ]);
                }

                $balance->update([
                    'quantity_base' => $quantityAfterFormatted,
                ]);

                return StockMovement::create([
                    'product_id' => $productId,

                    'product_unit_id' => $productUnitId,

                    'movement_type' => $movementType,

                    'source_type' => $sourceType,

                    'source_id' => $sourceId,

                    'source_quantity' => $sourceQuantity,

                    'conversion_to_base' => $conversionToBase,

                    'quantity_delta_base' => $deltaFormatted,

                    'quantity_before_base' => $quantityBeforeFormatted,

                    'quantity_after_base' => $quantityAfterFormatted,

                    'actor_user_id' => $actorUserId,

                    'terminal_id' => $terminalId,

                    'reason' => $reason,

                    'occurred_at' => $occurredAt ?? now(),
                ]);
            },
            3
        );
    }
}
