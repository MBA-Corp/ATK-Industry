<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'is_active',
])]
#[Hidden([
    'password',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
    public function roles(): BelongsToMany
{
    return $this->belongsToMany(
        Role::class,
        'user_roles',
        'user_id',
        'role_id'
    );
}

public function hasRole(string $roleCode): bool
{
    return $this->roles()
        ->where('code', $roleCode)
        ->exists();
}

public function hasPermission(string $permissionCode): bool
{
    return $this->roles()
        ->whereHas('permissions', function ($query) use ($permissionCode) {
            $query->where('code', $permissionCode);
        })
        ->exists();
}
}