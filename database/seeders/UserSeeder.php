<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist (safe if already seeded)
        $roles = ['super-admin', 'admin', 'editor', 'author', 'seo-manager'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Create or update the main admin user
        $user = User::query()->updateOrCreate(
            ['email' => 'admin@siatex.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        // Assign super-admin role
        $user->syncRoles(['super-admin']);
    }
}