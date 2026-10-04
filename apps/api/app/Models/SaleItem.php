<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_id',
    'product_id',
    'product_unit_id',
    'product_sku',
    'product_name',
    'unit_code',
    'quantity',
    'conversion_to_base',
    'catalog_unit_price',
    'sale_unit_price',
    'line_total',
])]
class SaleItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'conversion_to_base' => 'decimal:6',
            'catalog_unit_price' => 'integer',
            'sale_unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class
        );
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
}
