<?php

namespace Database\Seeders;

use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The single bootstrap system_admin (Section 9.5). Password comes from .env and
 * must be changed at first login. Every other user is created through the API.
 */
class BootstrapAdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('mpclg.bootstrap_admin');

        if (User::where('username', $config['username'])->exists()) {
            return;
        }

        if (blank($config['password'])) {
            throw new RuntimeException('Set ADMIN_PASSWORD in .env before seeding the bootstrap administrator.');
        }

        $user = User::create([
            'username' => $config['username'],
            'email' => $config['email'],
            'password' => $config['password'],
            'status' => UserStatus::Active,
            'must_change_password' => true,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::where('code', 'system_admin')->value('id'),
            'scope_type' => ScopeType::Global,
            'valid_from' => now(),
            'granted_at' => now(),
        ]);
    }
}
