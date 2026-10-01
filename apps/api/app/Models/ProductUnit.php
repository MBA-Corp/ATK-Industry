<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_id',
    'unit_id',
    'conversion_to_base',
    'selling_price',
    'is_base',
    'is_active',
])]
class ProductUnit extends Model
{
    public $timestamps = false;

    protected $attributes = [
        'is_base' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'conversion_to_base' => 'decimal:6',
            'selling_price' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(
            ProductPriceHistory::class
        );
    }
}
