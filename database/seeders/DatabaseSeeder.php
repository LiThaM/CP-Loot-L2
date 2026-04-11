<?php

namespace Database\Seeders;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            FakeItemsSeeder::class,
            TranslationSeeder::class,
        ]);

        // Create an initial admin if it doesn't exist
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && ! User::where('email', 'admin@l2.com')->exists()) {
            User::create([
                'name' => 'Super Admin',
                'email' => 'admin@l2.com',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
            ]);
        }
    }
}
