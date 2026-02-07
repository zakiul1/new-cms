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
        // Filament uses "web" guard by default
        $guard = 'web';

        // Ensure roles exist (safe if already seeded)
        $roles = ['super-admin', 'admin', 'editor', 'author', 'seo-manager'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => $guard,
            ]);
        }

        // Create or update the main admin user
        $user = User::query()->updateOrCreate(
            ['email' => 'admin@siatex.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        // Assign super-admin role (same guard)
        $user->syncRoles(['super-admin']);
    }
}