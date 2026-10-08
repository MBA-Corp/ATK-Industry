<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'profile_key',
    'name',
    'address',
    'phone',
    'logo_path',
])]
class CompanyProfile extends Model
{
    //
}
