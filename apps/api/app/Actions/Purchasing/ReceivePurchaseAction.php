<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceivePurchaseAction
{
    public function __construct(
        private ApplyStockMovementAction $applyStockMovement
    ) {}

    public function execute(
        int $purchaseId,
        array $validated,
        ?int $receivedBy = null
    ): Purchase {
        return DB::transaction(
            function () use (
                $purchaseId,
                $validated,
                $receivedBy
            ) {
                $purchase = Purchase::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $purchaseId
                    );

                if (
                    $purchase->status === 'RECEIVED'
                    || $purchase
                        ->receipt()
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'purchase' => 'Purchase has already been received.',
                    ]);
                }

                $purchaseItems = $purchase
                    ->items()
                    ->with(
                        'productUnit:id,product_id,conversion_to_base'
                    )
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $receivedAt =
                    $validated['received_at']
                    ?? now();

                $receipt = $purchase
                    ->receipt()
                    ->create([
                        'receipt_number' => $this->generateReceiptNumber(),

                        'received_by' => $receivedBy,

                        'received_at' => $receivedAt,

                        'notes' => $validated['notes']
                            ?? null,
                    ]);

                foreach (
                    $validated['items'] as $itemData
                ) {
                    $purchaseItem =
                        $purchaseItems->get(
                            (int) $itemData[
                                'purchase_item_id'
                            ]
                        );

                    if ($purchaseItem === null) {
                        throw ValidationException::withMessages([
                            'items' => 'Purchase item does not belong to this purchase.',
                        ]);
                    }

                    $actualQuantity =
                        $itemData[
                            'qty_received'
                        ];

                    $receiptItem = $receipt
                        ->items()
                        ->create([
                            'purchase_item_id' => $purchaseItem->id,

                            'qty_received' => $actualQuantity,
                        ]);

                    $purchaseItem->update([
                        'qty_received' => $actualQuantity,
                    ]);

                    $productUnit =
                        $purchaseItem
                            ->productUnit;

                    $conversionToBase =
                        $purchaseItem
                            ->conversion_to_base
                        ?? $productUnit
                            ->conversion_to_base;

                    $quantityDeltaBase =
                        number_format(
                            (float) $actualQuantity
                            * (float) $conversionToBase,
                            6,
                            '.',
                            ''
                        );

                    $this
                        ->applyStockMovement
                        ->execute(
                            productId: $productUnit
                                ->product_id,

                            movementType: 'PURCHASE_RECEIPT',

                            sourceType: 'PURCHASE_RECEIPT_ITEM',

                            sourceId: $receiptItem->id,

                            quantityDeltaBase: $quantityDeltaBase,

                            productUnitId: $productUnit->id,

                            sourceQuantity: $actualQuantity,

                            conversionToBase: $conversionToBase,

                            actorUserId: $receivedBy,

                            reason: 'Purchase receiving',

                            occurredAt: $receivedAt
                        );
                }

                $purchase->update([
                    'status' => 'RECEIVED',

                    'received_at' => $receivedAt,
                ]);

                return $purchase
                    ->refresh()
                    ->load([
                        'supplier:id,code,name',
                        'createdBy:id,name,username',

                        'items.productSupplier.product:id,sku,name',

                        'items.productUnit.unit:id,code,name',
                    ]);
            },
            3
        );
    }

    private function generateReceiptNumber(): string
    {
        return 'REC-'
            .now()->format('Ymd')
            .'-'
            .Str::upper(
                (string) Str::ulid()
            );
    }
}
