<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_receipt_id',
    'purchase_item_id',
    'qty_received',
])]
class PurchaseReceiptItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:6',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseReceipt::class,
            'purchase_receipt_id'
        );
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseItem::class
        );
    }
}
