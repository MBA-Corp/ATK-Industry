<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'adjustment_number',
    'product_id',
    'product_unit_id',
    'adjustment_type',
    'quantity',
    'conversion_to_base',
    'quantity_delta_base',
    'quantity_before_base',
    'quantity_after_base',
    'reason',
    'created_by',
    'occurred_at',
])]
class StockAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'conversion_to_base' => 'decimal:6',
            'quantity_delta_base' => 'decimal:6',
            'quantity_before_base' => 'decimal:6',
            'quantity_after_base' => 'decimal:6',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(
            ProductUnit::class
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
