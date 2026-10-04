<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_id',
    'cancelled_by',
    'reason',
    'cancelled_at',
])]
class SaleCancellation extends Model
{
    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class
        );
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }
}
