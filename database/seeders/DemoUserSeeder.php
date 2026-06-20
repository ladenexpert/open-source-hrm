<?php

namespace Database\Seeders;

use App\Models\AttendancePolicy;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\Employee;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    private const DEMO_LATITUDE = -6.2000000;

    private const DEMO_LONGITUDE = 106.8166667;

    private const DEMO_RADIUS_METERS = 100;

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

        $defaultBranch = Branch::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->first();

        $defaultWorkLocation = WorkLocation::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->first();

        if ($defaultWorkLocation instanceof WorkLocation) {
            $defaultWorkLocation->forceFill([
                'branch_id' => $defaultWorkLocation->branch_id ?: $defaultBranch?->id,
                'latitude' => self::DEMO_LATITUDE,
                'longitude' => self::DEMO_LONGITUDE,
                'radius_meters' => self::DEMO_RADIUS_METERS,
                'is_active' => true,
            ])->save();
        }

        $defaultAttendancePolicy = AttendancePolicy::query()
            ->where('company_id', $company->id)
            ->where('code', 'OFFICE')
            ->first();

        $defaultAttributes = [
            'company_id' => $company->id,
            'company_group_id' => $companyGroup->id,
            'branch_id' => $defaultBranch?->id,
            'work_location_id' => $defaultWorkLocation?->id,
            'attendance_policy_id' => $defaultAttendancePolicy?->id,
            'attendance_location_mode_override' => null,
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
