<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Admin',
            'password' => 'admin',
            'is_active' => true,
        ]);

        $owner = Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner']);
        $user->roles()->syncWithoutDetaching([$owner->id]);
    }
}
