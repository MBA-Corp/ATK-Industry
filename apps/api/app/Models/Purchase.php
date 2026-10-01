<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_number',
    'supplier_id',
    'supplier_invoice_number',
    'purchase_date',
    'status',
    'subtotal',
    'operational_cost',
    'grand_total',
    'notes',
    'created_by',
    'received_at',
])]
class Purchase extends Model
{
    protected $attributes = [
        'status' => 'DRAFT',
        'subtotal' => 0,
        'operational_cost' => 0,
        'grand_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'integer',
            'operational_cost' => 'integer',
            'grand_total' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseItem::class
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
