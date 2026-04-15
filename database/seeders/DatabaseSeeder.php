<?php

namespace Database\Seeders;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TranslationSeeder::class,
        ]);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $adminEmail = (string) env('SEED_ADMIN_EMAIL', 'admin@l2.com');
        $adminPassword = (string) env('SEED_ADMIN_PASSWORD', 'password');

        $adminRoleId = Role::where('name', 'admin')->value('id');
        if ($adminRoleId && ! User::where('email', $adminEmail)->exists()) {
            User::create([
                'name' => 'Super Admin',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role_id' => $adminRoleId,
            ]);
        }
    }
}
