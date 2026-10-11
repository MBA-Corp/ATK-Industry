<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Support\DecimalMath;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

class CancelSaleAction
{
    public function __construct(
        private ApplyStockMovementAction $applyStockMovement
    ) {}

    public function execute(
        int $saleId,
        array $validated,
        int $cancelledBy
    ): Sale {
        return DB::transaction(
            function () use (
                $saleId,
                $validated,
                $cancelledBy
            ) {
                $sale = Sale::query()
                    ->with([
                        'items',
                        'cancellation',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($saleId);

                if ($sale->status !== 'COMPLETED') {
                    throw ValidationException::withMessages([
                        'sale' => 'Only completed sales can be cancelled.',
                    ]);
                }

                if ($sale->cancellation !== null) {
                    throw ValidationException::withMessages([
                        'sale' => 'Sale has already been cancelled.',
                    ]);
                }

                $cancelledAt = now();

                $cancellation = $sale
                    ->cancellation()
                    ->create([
                        'cancelled_by' => $cancelledBy,

                        'reason' => $validated[
                            'reason'
                        ],

                        'cancelled_at' => $cancelledAt,
                    ]);

                $items = $sale
                    ->items
                    ->sortBy('product_id');

                foreach ($items as $item) {
                    $originalMovement = StockMovement::query()
                        ->where('source_type', 'SALE_ITEM')
                        ->where('source_id', $item->id)
                        ->first();

                    if ($originalMovement !== null) {
                        $originalDelta = (string) $originalMovement
                            ->quantity_delta_base;

                        if (
                            (int) $originalMovement->product_id !== (int) $item->product_id
                            || ! str_starts_with($originalDelta, '-')
                            || $originalDelta === '-0.000000'
                        ) {
                            throw ValidationException::withMessages([
                                'sale' => 'Original sale stock movement is invalid.',
                            ]);
                        }

                        $quantityDeltaBase = substr($originalDelta, 1);
                    } else {
                        try {
                            $quantityDeltaBase = DecimalMath::multiply(
                                (string) $item->quantity,
                                (string) $item->conversion_to_base
                            );
                        } catch (DomainException|InvalidArgumentException|OverflowException $exception) {
                            throw ValidationException::withMessages([
                                'sale' => 'Sale cancellation exceeds supported inventory precision or range.',
                            ]);
                        }

                        if ($quantityDeltaBase === '0.000000') {
                            throw ValidationException::withMessages([
                                'sale' => 'Sale cancellation cannot reverse zero stock quantity.',
                            ]);
                        }
                    }

                    $this->applyStockMovement->execute(
                        productId: $item->product_id,

                        movementType: 'SALE_REVERSAL',

                        sourceType: 'SALE_CANCELLATION_ITEM',

                        sourceId: $item->id,

                        quantityDeltaBase: $quantityDeltaBase,

                        productUnitId: $item->product_unit_id,

                        sourceQuantity: $item->quantity,

                        conversionToBase: $item->conversion_to_base,

                        actorUserId: $cancelledBy,

                        terminalId: $sale->terminal_id,

                        reason: $validated[
                            'reason'
                        ],

                        occurredAt: $cancelledAt
                    );
                }

                $sale->update([
                    'status' => 'CANCELLED',
                ]);

                return $sale->load([
                    'terminal:id,terminal_uuid,name',
                    'cashier:id,name,username',
                    'items',
                    'payments',

                    'cancellation.cancelledBy:id,name,username',
                ]);
            },
            3
        );
    }
}
