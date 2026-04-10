<?php

namespace Database\Seeders;

use App\Contexts\Identity\Domain\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'System Administrator with global privileges'
            ],
            [
                'name' => 'cp_leader',
                'display_name' => 'CP Leader',
                'description' => 'Leader of a Const Party'
            ],
            [
                'name' => 'member',
                'display_name' => 'Member',
                'description' => 'Regular member of a Const Party'
            ],
            [
                'name' => 'accountant',
                'display_name' => 'CP Accountant',
                'description' => 'Responsible for Adena distribution'
            ]
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
