<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'super_admin',
            'admin',
            'hr',
            'finance',
            'department_manager',
            'employee',
            'guest',
        ] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
