<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'address',
    'city',
    'phone',
    'is_active',
])]
class Supplier extends Model
{
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(
            ProductSupplier::class
        );
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(
            Purchase::class
        );
    }
}
