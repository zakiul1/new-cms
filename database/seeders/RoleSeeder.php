<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web'; // Filament panel uses the "web" guard by default

        $roles = ['super-admin', 'admin', 'editor', 'author', 'seo-manager'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => $guard,
            ]);
        }
    }
}