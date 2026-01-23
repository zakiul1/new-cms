<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['super-admin', 'admin', 'editor', 'author', 'seo-manager'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}