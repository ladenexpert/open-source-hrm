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
            'hr_admin',
            'hr_head',
            'finance',
            'finance_admin',
            'finance_head',
            'department_manager',
            'department_head',
            'leader',
            'company_head',
            'employee',
            'guest',
        ] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
