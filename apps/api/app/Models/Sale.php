<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sale_number',
    'client_reference',
    'terminal_id',
    'cashier_user_id',
    'status',
    'subtotal',
    'grand_total',
    'sold_at',
    'completed_at',
])]
class Sale extends Model
{
    protected $attributes = [
        'status' => 'DRAFT',
        'subtotal' => 0,
        'grand_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'grand_total' => 'integer',
            'sold_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(
            Terminal::class
        );
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cashier_user_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            SaleItem::class
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            SalePayment::class
        );
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(
            SaleCancellation::class
        );
    }
}
