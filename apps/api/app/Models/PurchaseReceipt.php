<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_id',
    'receipt_number',
    'received_by',
    'received_at',
    'notes',
])]
class PurchaseReceipt extends Model
{
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(
            Purchase::class
        );
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'received_by'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseReceiptItem::class
        );
    }
}
