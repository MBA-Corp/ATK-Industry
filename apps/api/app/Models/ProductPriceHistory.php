<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_unit_id',
    'old_price',
    'new_price',
    'changed_by',
    'reason',
    'changed_at',
])]
class ProductPriceHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_price' => 'integer',
            'new_price' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(
            ProductUnit::class
        );
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'changed_by'
        );
    }
}
