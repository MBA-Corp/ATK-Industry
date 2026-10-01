<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_id',
    'supplier_id',
    'supplier_sku',
    'last_purchase_price',
    'is_preferred',
    'is_active',
])]
class ProductSupplier extends Model
{
    protected $attributes = [
        'is_preferred' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'last_purchase_price' => 'integer',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(
            SupplierPriceHistory::class
        );
    }
}
