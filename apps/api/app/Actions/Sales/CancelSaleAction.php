<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                    $quantityDeltaBase =
                        number_format(
                            (float) $item->quantity
                            * (float) $item
                                ->conversion_to_base,
                            6,
                            '.',
                            ''
                        );

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
