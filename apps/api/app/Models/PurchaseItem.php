<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_id',
    'product_supplier_id',
    'product_unit_id',
    'conversion_to_base',
    'qty_ordered',
    'qty_received',
    'purchase_price',
    'line_total',
])]
class PurchaseItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:6',
            'qty_received' => 'decimal:6',
            'purchase_price' => 'integer',
            'line_total' => 'integer',
            'conversion_to_base' => 'decimal:6',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(
            Purchase::class
        );
    }

    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(
            ProductSupplier::class
        );
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(
            ProductUnit::class
        );
    }
}
