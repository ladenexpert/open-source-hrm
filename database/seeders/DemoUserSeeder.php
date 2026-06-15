<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $companyGroup = CompanyGroup::findOrCreateDefault();
        $company = Company::findOrCreateDefault();

        if ((int) $company->company_group_id !== (int) $companyGroup->id) {
            $company->forceFill([
                'company_group_id' => $companyGroup->id,
            ])->save();
        }

        $defaultAttributes = [
            'company_id' => $company->id,
            'company_group_id' => $companyGroup->id,
            'employment_type' => 'Permanent',
            'hire_date' => now()->toDateString(),
            'join_date' => now()->toDateString(),
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ];

        $superAdmin = Employee::query()->updateOrCreate(
            ['email' => 'admin@hrms.local'],
            array_merge($defaultAttributes, [
                'employee_code' => 'DEMO-ADMIN-001',
                'full_name' => 'HRMS Demo Super Admin',
                'first_name' => 'HRMS',
                'last_name' => 'Admin',
            ]),
        );
        $superAdmin->syncRoles(['super_admin']);

        $employee = Employee::query()->updateOrCreate(
            ['email' => 'employee@hrms.local'],
            array_merge($defaultAttributes, [
                'employee_code' => 'DEMO-EMP-001',
                'full_name' => 'HRMS Demo Employee',
                'first_name' => 'HRMS',
                'last_name' => 'Employee',
            ]),
        );
        $employee->syncRoles(['employee']);
    }
}
