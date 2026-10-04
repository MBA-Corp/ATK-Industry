<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'product_unit_id',
    'movement_type',
    'source_type',
    'source_id',
    'source_quantity',
    'conversion_to_base',
    'quantity_delta_base',
    'quantity_before_base',
    'quantity_after_base',
    'actor_user_id',
    'terminal_id',
    'reason',
    'occurred_at',
])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'source_quantity' => 'decimal:6',

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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id'
        );
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(
            Terminal::class
        );
    }
}
