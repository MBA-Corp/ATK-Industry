<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'quantity_base',
])]
class InventoryBalance extends Model
{
    protected $attributes = [
        'quantity_base' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity_base' => 'decimal:6',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }
}
