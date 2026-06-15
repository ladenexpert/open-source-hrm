<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\IdentityType;
use App\Models\JobGrade;
use App\Models\JobLevel;
use App\Models\MaritalStatus;
use App\Models\Position;
use App\Models\Religion;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;

class SampleWorkforceSeeder extends Seeder
{
    public function run(): void
    {
        $companyGroup = CompanyGroup::findOrCreateDefault();
        $holdingCompany = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
        $subsidiaryA = Company::query()->where('code', 'SUB-A')->firstOrFail();

        $holdingBranch = Branch::query()->where('company_id', $holdingCompany->id)->firstOrFail();
        $holdingLocation = WorkLocation::query()->where('company_id', $holdingCompany->id)->firstOrFail();
        $subsidiaryBranch = Branch::query()->where('company_id', $subsidiaryA->id)->firstOrFail();
        $subsidiaryLocation = WorkLocation::query()->where('company_id', $subsidiaryA->id)->firstOrFail();

        $hrDepartment = Department::query()->where('company_id', $holdingCompany->id)->where('code', 'HR')->firstOrFail();
        $engineeringDepartment = Department::query()->where('company_id', $holdingCompany->id)->where('code', 'ENG')->firstOrFail();
        $hrDivision = Division::query()->where('code', 'HR-BP')->firstOrFail();
        $engineeringDivision = Division::query()->where('code', 'ENG-PLATFORM')->firstOrFail();

        $managerLevel = JobLevel::query()->where('code', 'L3')->firstOrFail();
        $staffLevel = JobLevel::query()->where('code', 'L1')->firstOrFail();
        $seniorLevel = JobLevel::query()->where('code', 'L2')->firstOrFail();
        $gradeOne = JobGrade::query()->where('code', 'G1')->firstOrFail();
        $gradeTwo = JobGrade::query()->where('code', 'G2')->firstOrFail();

        $positions = [
            'HR_MANAGER' => Position::query()->firstOrCreate(
                ['company_id' => $holdingCompany->id, 'code' => 'HR-MGR'],
                [
                    'branch_id' => $holdingBranch->id,
                    'department_id' => $hrDepartment->id,
                    'division_id' => $hrDivision->id,
                    'job_level_id' => $managerLevel->id,
                    'job_grade_id' => $gradeTwo->id,
                    'title' => 'HR Manager',
                    'salary' => 18000000,
                    'is_active' => true,
                ]
            ),
            'SOFTWARE_ENGINEER' => Position::query()->firstOrCreate(
                ['company_id' => $holdingCompany->id, 'code' => 'SWE-01'],
                [
                    'branch_id' => $holdingBranch->id,
                    'department_id' => $engineeringDepartment->id,
                    'division_id' => $engineeringDivision->id,
                    'job_level_id' => $seniorLevel->id,
                    'job_grade_id' => $gradeTwo->id,
                    'title' => 'Software Engineer',
                    'salary' => 14500000,
                    'is_active' => true,
                ]
            ),
            'INTERN' => Position::query()->firstOrCreate(
                ['company_id' => $holdingCompany->id, 'code' => 'INT-01'],
                [
                    'branch_id' => $holdingBranch->id,
                    'department_id' => $engineeringDepartment->id,
                    'division_id' => $engineeringDivision->id,
                    'job_level_id' => $staffLevel->id,
                    'job_grade_id' => $gradeOne->id,
                    'title' => 'Engineering Intern',
                    'salary' => 3500000,
                    'is_active' => true,
                ]
            ),
            'OUTSOURCE' => Position::query()->firstOrCreate(
                ['company_id' => $subsidiaryA->id, 'code' => 'OPS-OUT-01'],
                [
                    'branch_id' => $subsidiaryBranch->id,
                    'job_level_id' => $staffLevel->id,
                    'job_grade_id' => $gradeOne->id,
                    'title' => 'Operations Support',
                    'salary' => 5000000,
                    'is_active' => true,
                ]
            ),
        ];

        $employmentStatuses = EmploymentStatus::query()->pluck('id', 'code');
        $employmentTypes = EmploymentType::query()->pluck('id', 'code');
        $contractTypes = ContractType::query()->pluck('id', 'code');
        $identityTypes = IdentityType::query()->pluck('id', 'code');
        $religions = Religion::query()->pluck('id', 'code');
        $maritalStatuses = MaritalStatus::query()->pluck('id', 'code');
        $banks = Bank::query()->pluck('id', 'code');

        $hrManager = Employee::query()->updateOrCreate(
            ['email' => 'sari.manager@example.test'],
            [
                'company_id' => $holdingCompany->id,
                'company_group_id' => $companyGroup->id,
                'branch_id' => $holdingBranch->id,
                'work_location_id' => $holdingLocation->id,
                'department_id' => $hrDepartment->id,
                'division_id' => $hrDivision->id,
                'position_id' => $positions['HR_MANAGER']->id,
                'job_level_id' => $managerLevel->id,
                'job_grade_id' => $gradeTwo->id,
                'employee_code' => 'EMP-HR-001',
                'full_name' => 'Sari Wulandari',
                'first_name' => 'Sari',
                'last_name' => 'Wulandari',
                'phone' => '081234567801',
                'identity_type_id' => $identityTypes['KTP'],
                'nik_ktp' => '3174010101900001',
                'bank_id' => $banks['BCA'],
                'bank_account_number' => '1234567890',
                'bank_account_holder_name' => 'Sari Wulandari',
                'npwp_number' => '09.123.456.7-890.000',
                'employment_status_id' => $employmentStatuses['ACTIVE'],
                'employment_type_id' => $employmentTypes['PKWTT'],
                'contract_type_id' => $contractTypes['PERMANENT'],
                'religion_id' => $religions['ISLAM'],
                'marital_status_id' => $maritalStatuses['MARRIED'],
                'join_date' => '2023-01-10',
                'hire_date' => '2023-01-10',
                'is_active' => true,
                'password' => 'password123',
            ]
        );

        $this->assignEmployeeRole($hrManager);

        $sampleEmployees = [
            [
                'email' => 'andi.permanent@example.test',
                'employee_code' => 'EMP-LOC-001',
                'full_name' => 'Andi Pratama',
                'first_name' => 'Andi',
                'last_name' => 'Pratama',
                'company_id' => $holdingCompany->id,
                'company_group_id' => $companyGroup->id,
                'branch_id' => $holdingBranch->id,
                'work_location_id' => $holdingLocation->id,
                'department_id' => $engineeringDepartment->id,
                'division_id' => $engineeringDivision->id,
                'position_id' => $positions['SOFTWARE_ENGINEER']->id,
                'job_level_id' => $seniorLevel->id,
                'job_grade_id' => $gradeTwo->id,
                'direct_supervisor_id' => $hrManager->id,
                'identity_type_id' => $identityTypes['KTP'],
                'nik_ktp' => '3174010101900002',
                'employment_status_id' => $employmentStatuses['ACTIVE'],
                'employment_type_id' => $employmentTypes['PKWTT'],
                'contract_type_id' => $contractTypes['PERMANENT'],
                'religion_id' => $religions['ISLAM'],
                'marital_status_id' => $maritalStatuses['SINGLE'],
                'bank_id' => $banks['MANDIRI'],
                'bank_account_number' => '9988776611',
                'bank_account_holder_name' => 'Andi Pratama',
                'join_date' => '2024-02-01',
                'hire_date' => '2024-02-01',
                'is_active' => true,
            ],
            [
                'email' => 'maya.contract@example.test',
                'employee_code' => 'EMP-CON-001',
                'full_name' => 'Maya Lestari',
                'first_name' => 'Maya',
                'last_name' => 'Lestari',
                'company_id' => $holdingCompany->id,
                'company_group_id' => $companyGroup->id,
                'branch_id' => $holdingBranch->id,
                'work_location_id' => $holdingLocation->id,
                'department_id' => $engineeringDepartment->id,
                'division_id' => $engineeringDivision->id,
                'position_id' => $positions['SOFTWARE_ENGINEER']->id,
                'job_level_id' => $staffLevel->id,
                'job_grade_id' => $gradeOne->id,
                'direct_supervisor_id' => $hrManager->id,
                'identity_type_id' => $identityTypes['KTP'],
                'nik_ktp' => '3174010101900003',
                'employment_status_id' => $employmentStatuses['ACTIVE'],
                'employment_type_id' => $employmentTypes['PKWT'],
                'contract_type_id' => $contractTypes['FIXED_TERM'],
                'religion_id' => $religions['CHRISTIAN'],
                'marital_status_id' => $maritalStatuses['MARRIED'],
                'bank_id' => $banks['BNI'],
                'bank_account_number' => '1122334455',
                'bank_account_holder_name' => 'Maya Lestari',
                'join_date' => '2025-01-15',
                'hire_date' => '2025-01-15',
                'contract_start_date' => '2025-01-15',
                'contract_end_date' => '2026-01-14',
                'is_active' => true,
            ],
            [
                'email' => 'rio.outsource@example.test',
                'employee_code' => 'EMP-OUT-001',
                'full_name' => 'Rio Nugraha',
                'first_name' => 'Rio',
                'last_name' => 'Nugraha',
                'company_id' => $subsidiaryA->id,
                'company_group_id' => $companyGroup->id,
                'branch_id' => $subsidiaryBranch->id,
                'work_location_id' => $subsidiaryLocation->id,
                'position_id' => $positions['OUTSOURCE']->id,
                'job_level_id' => $staffLevel->id,
                'job_grade_id' => $gradeOne->id,
                'identity_type_id' => $identityTypes['KTP'],
                'nik_ktp' => '3174010101900004',
                'employment_status_id' => $employmentStatuses['ACTIVE'],
                'employment_type_id' => $employmentTypes['OUTSOURCE'],
                'contract_type_id' => $contractTypes['OUTSOURCE_AGREEMENT'],
                'religion_id' => $religions['ISLAM'],
                'marital_status_id' => $maritalStatuses['SINGLE'],
                'bank_id' => $banks['BRI'],
                'bank_account_number' => '2233445566',
                'bank_account_holder_name' => 'Rio Nugraha',
                'join_date' => '2024-08-01',
                'hire_date' => '2024-08-01',
                'is_active' => true,
            ],
            [
                'email' => 'nina.intern@example.test',
                'employee_code' => 'EMP-INT-001',
                'full_name' => 'Nina Saputri',
                'first_name' => 'Nina',
                'last_name' => 'Saputri',
                'company_id' => $holdingCompany->id,
                'company_group_id' => $companyGroup->id,
                'branch_id' => $holdingBranch->id,
                'work_location_id' => $holdingLocation->id,
                'department_id' => $engineeringDepartment->id,
                'division_id' => $engineeringDivision->id,
                'position_id' => $positions['INTERN']->id,
                'job_level_id' => $staffLevel->id,
                'job_grade_id' => $gradeOne->id,
                'direct_supervisor_id' => $hrManager->id,
                'identity_type_id' => $identityTypes['KTP'],
                'nik_ktp' => '3174010101900005',
                'employment_status_id' => $employmentStatuses['ACTIVE'],
                'employment_type_id' => $employmentTypes['INTERN'],
                'contract_type_id' => $contractTypes['INTERNSHIP_AGREEMENT'],
                'religion_id' => $religions['ISLAM'],
                'marital_status_id' => $maritalStatuses['SINGLE'],
                'bank_id' => $banks['BCA'],
                'bank_account_number' => '3344556677',
                'bank_account_holder_name' => 'Nina Saputri',
                'join_date' => '2026-01-06',
                'hire_date' => '2026-01-06',
                'contract_start_date' => '2026-01-06',
                'contract_end_date' => '2026-07-05',
                'is_active' => true,
            ],
            [
                'email' => 'kim.minjun@example.test',
                'employee_code' => 'EMP-EXP-001',
                'full_name' => 'Kim Minjun',
                'first_name' => 'Kim',
                'last_name' => 'Minjun',
                'company_id' => $holdingCompany->id,
                'company_group_id' => $companyGroup->id,
                'branch_id' => $holdingBranch->id,
                'work_location_id' => $holdingLocation->id,
                'department_id' => $engineeringDepartment->id,
                'division_id' => $engineeringDivision->id,
                'position_id' => $positions['SOFTWARE_ENGINEER']->id,
                'job_level_id' => $seniorLevel->id,
                'job_grade_id' => $gradeTwo->id,
                'direct_supervisor_id' => $hrManager->id,
                'identity_type_id' => $identityTypes['PASSPORT'],
                'passport_number' => 'M12345678',
                'passport_expiry_date' => '2030-12-31',
                'kitas_kitap_number' => 'KITAS-2026-001',
                'employment_status_id' => $employmentStatuses['ACTIVE'],
                'employment_type_id' => $employmentTypes['EXPATRIATE'],
                'contract_type_id' => $contractTypes['EXPAT_ASSIGNMENT'],
                'religion_id' => $religions['CHRISTIAN'],
                'marital_status_id' => $maritalStatuses['MARRIED'],
                'bank_id' => $banks['CIMB'],
                'bank_account_number' => '4455667788',
                'bank_account_holder_name' => 'Kim Minjun',
                'join_date' => '2026-03-01',
                'hire_date' => '2026-03-01',
                'nationality' => 'South Korea',
                'citizenship_status' => 'foreign_national',
                'expatriate_status' => 'expatriate',
                'visa_type' => 'C312',
                'visa_number' => 'VISA-KR-2026-001',
                'visa_issued_date' => '2026-02-15',
                'visa_expiry_date' => '2027-02-14',
                'work_permit_number' => 'IMTA-2026-001',
                'work_permit_expiry_date' => '2027-02-14',
                'home_country' => 'South Korea',
                'home_company' => 'Seoul Tech Holdings',
                'host_company_id' => $holdingCompany->id,
                'assignment_start_date' => '2026-03-01',
                'assignment_end_date' => '2027-02-28',
                'payroll_scheme' => 'split_payroll',
                'tax_residency_status' => 'non_resident',
                'bpjs_eligible' => false,
                'thr_eligible' => false,
                'is_active' => true,
            ],
        ];

        foreach ($sampleEmployees as $employeeData) {
            $employee = Employee::query()->updateOrCreate(
                ['email' => $employeeData['email']],
                array_merge($employeeData, ['password' => 'password123'])
            );

            $this->assignEmployeeRole($employee);
        }
    }

    private function assignEmployeeRole(Employee $employee): void
    {
        if (! $employee->hasNormalizedRole('employee')) {
            $employee->assignRole('employee');
        }
    }
}
