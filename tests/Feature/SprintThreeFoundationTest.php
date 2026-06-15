<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Bank;
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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SprintThreeFoundationTest extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_company_group_holding_and_subsidiary_structure_is_seeded(): void
    {
        $defaultGroup = CompanyGroup::query()->where('code', CompanyGroup::DEFAULT_CODE)->first();
        $holdingCompany = Company::query()->where('code', Company::DEFAULT_CODE)->first();
        $subsidiaryA = Company::query()->where('code', 'SUB-A')->first();
        $subsidiaryB = Company::query()->where('code', 'SUB-B')->first();

        $this->assertNotNull($defaultGroup);
        $this->assertNotNull($holdingCompany);
        $this->assertNotNull($subsidiaryA);
        $this->assertNotNull($subsidiaryB);
        $this->assertSame($defaultGroup->id, $holdingCompany->company_group_id);
        $this->assertSame('holding', $holdingCompany->company_type);
        $this->assertSame($holdingCompany->id, $subsidiaryA->parent_company_id);
        $this->assertSame($holdingCompany->id, $subsidiaryB->parent_company_id);
    }

    public function test_default_company_is_assigned_to_default_group(): void
    {
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
        $group = CompanyGroup::query()->where('code', CompanyGroup::DEFAULT_CODE)->firstOrFail();

        $this->assertSame($group->id, $company->company_group_id);
    }

    public function test_hr_can_manage_group_scoped_master_data_but_not_other_group_data(): void
    {
        $holdingCompany = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
        $defaultGroup = CompanyGroup::query()->where('code', CompanyGroup::DEFAULT_CODE)->firstOrFail();
        $hr = $this->makeEmployee('hr', [
            'company_id' => $holdingCompany->id,
            'company_group_id' => $defaultGroup->id,
        ]);

        $sameGroupStatus = EmploymentStatus::query()->create([
            'company_group_id' => $defaultGroup->id,
            'code' => 'GROUP-ACTIVE',
            'name' => 'Group Active',
        ]);

        $otherGroup = CompanyGroup::query()->create([
            'code' => 'OTHER-GROUP',
            'name' => 'Other Group',
            'is_active' => true,
        ]);

        $otherGroupStatus = EmploymentStatus::query()->create([
            'company_group_id' => $otherGroup->id,
            'code' => 'OTHER-ACTIVE',
            'name' => 'Other Active',
        ]);

        $this->assertTrue(Gate::forUser($hr)->allows('update', $sameGroupStatus));
        $this->assertFalse(Gate::forUser($hr)->allows('update', $otherGroupStatus));
    }

    public function test_employee_cannot_manage_master_data(): void
    {
        $employee = $this->makeEmployee('employee');
        $employmentStatus = EmploymentStatus::query()->where('code', 'ACTIVE')->firstOrFail();

        $this->assertFalse(Gate::forUser($employee)->allows('create', EmploymentStatus::class));
        $this->assertFalse(Gate::forUser($employee)->allows('update', $employmentStatus));
    }

    public function test_employee_profile_can_store_indonesian_fields_and_workforce_classification(): void
    {
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
        $employee = $this->makeEmployee('employee', [
            'company_id' => $company->id,
            'nik_ktp' => '3174010101909999',
            'npwp_number' => '11.111.111.1-111.111',
            'bank_id' => Bank::query()->where('code', 'BCA')->value('id'),
            'bank_account_number' => '999888777',
            'bank_account_holder_name' => 'Employee Test',
            'employment_status_id' => EmploymentStatus::query()->where('code', 'ACTIVE')->value('id'),
            'employment_type_id' => EmploymentType::query()->where('code', 'PKWTT')->value('id'),
            'contract_type_id' => ContractType::query()->where('code', 'PERMANENT')->value('id'),
            'identity_type_id' => IdentityType::query()->where('code', 'KTP')->value('id'),
            'religion_id' => Religion::query()->where('code', 'ISLAM')->value('id'),
            'marital_status_id' => MaritalStatus::query()->where('code', 'MARRIED')->value('id'),
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'nik_ktp' => '3174010101909999',
            'npwp_number' => '11.111.111.1-111.111',
            'employment_status_id' => EmploymentStatus::query()->where('code', 'ACTIVE')->value('id'),
            'employment_type_id' => EmploymentType::query()->where('code', 'PKWTT')->value('id'),
            'contract_type_id' => ContractType::query()->where('code', 'PERMANENT')->value('id'),
        ]);
    }

    public function test_employee_profile_can_store_expatriate_fields(): void
    {
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
        $employee = $this->makeEmployee('employee', [
            'company_id' => $company->id,
            'employment_type_id' => EmploymentType::query()->where('code', 'EXPATRIATE')->value('id'),
            'contract_type_id' => ContractType::query()->where('code', 'EXPAT_ASSIGNMENT')->value('id'),
            'identity_type_id' => IdentityType::query()->where('code', 'PASSPORT')->value('id'),
            'passport_number' => 'X1234567',
            'nationality' => 'South Korea',
            'citizenship_status' => 'foreign_national',
            'expatriate_status' => 'expatriate',
            'visa_type' => 'C312',
            'visa_number' => 'VISA-TEST-001',
            'visa_issued_date' => '2026-01-01',
            'visa_expiry_date' => '2026-12-31',
            'assignment_start_date' => '2026-01-10',
            'assignment_end_date' => '2026-12-31',
            'host_company_id' => $company->id,
            'bpjs_eligible' => false,
            'thr_eligible' => false,
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'passport_number' => 'X1234567',
            'visa_number' => 'VISA-TEST-001',
            'expatriate_status' => 'expatriate',
            'bpjs_eligible' => false,
            'thr_eligible' => false,
        ]);
    }

    public function test_invalid_expatriate_assignment_dates_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();

        $this->makeEmployee('employee', [
            'company_id' => $company->id,
            'employment_type_id' => EmploymentType::query()->where('code', 'EXPATRIATE')->value('id'),
            'contract_type_id' => ContractType::query()->where('code', 'EXPAT_ASSIGNMENT')->value('id'),
            'identity_type_id' => IdentityType::query()->where('code', 'PASSPORT')->value('id'),
            'passport_number' => 'Y7654321',
            'nationality' => 'South Korea',
            'citizenship_status' => 'foreign_national',
            'expatriate_status' => 'expatriate',
            'assignment_start_date' => '2026-06-10',
            'assignment_end_date' => '2026-06-01',
        ]);
    }

    public function test_supervisor_cannot_be_self(): void
    {
        $this->expectException(ValidationException::class);

        $employee = $this->makeEmployee('employee');
        $employee->update([
            'direct_supervisor_id' => $employee->id,
        ]);
    }

    public function test_supervisor_must_belong_to_same_company_or_group(): void
    {
        $this->expectException(ValidationException::class);

        $otherGroup = CompanyGroup::query()->create([
            'code' => 'FOREIGN-GROUP',
            'name' => 'Foreign Group',
            'is_active' => true,
        ]);

        $otherCompany = Company::query()->create([
            'company_group_id' => $otherGroup->id,
            'code' => 'FOREIGN-CO',
            'name' => 'Foreign Company',
            'company_type' => 'holding',
            'is_legal_entity' => true,
            'is_active' => true,
        ]);

        $supervisor = $this->makeEmployee('employee', [
            'company_id' => $otherCompany->id,
            'company_group_id' => $otherGroup->id,
            'email' => 'foreign-supervisor@example.test',
        ]);

        $this->makeEmployee('employee', [
            'direct_supervisor_id' => $supervisor->id,
            'email' => 'local-employee@example.test',
        ]);
    }

    public function test_cross_company_department_division_and_position_mismatch_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $holdingCompany = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
        $subsidiaryA = Company::query()->where('code', 'SUB-A')->firstOrFail();

        $foreignDepartment = Department::query()->create([
            'company_id' => $subsidiaryA->id,
            'company_group_id' => $subsidiaryA->company_group_id,
            'name' => 'Subsidiary A Operations',
            'code' => 'SUBA-OPS',
        ]);

        $foreignDivision = Division::query()->create([
            'company_id' => $subsidiaryA->id,
            'company_group_id' => $subsidiaryA->company_group_id,
            'department_id' => $foreignDepartment->id,
            'name' => 'Subsidiary A Support',
            'code' => 'SUBA-SUP',
        ]);

        $foreignPosition = Position::query()->create([
            'company_id' => $subsidiaryA->id,
            'department_id' => $foreignDepartment->id,
            'division_id' => $foreignDivision->id,
            'title' => 'Subsidiary Support Analyst',
            'code' => 'SUBA-SA',
            'is_active' => true,
        ]);

        $this->makeEmployee('employee', [
            'company_id' => $holdingCompany->id,
            'department_id' => $foreignDepartment->id,
            'division_id' => $foreignDivision->id,
            'position_id' => $foreignPosition->id,
            'email' => 'mismatch-employee@example.test',
        ]);
    }

    private function makeEmployee(string $role, array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;
        $companyId = $attributes['company_id'] ?? Company::query()->where('code', Company::DEFAULT_CODE)->value('id');
        $company = Company::query()->findOrFail($companyId);
        $branch = Branch::query()->where('company_id', $company->id)->first();
        $workLocation = WorkLocation::query()->where('company_id', $company->id)->first();
        $department = Department::query()->where('company_id', $company->id)->orderBy('id')->first();
        $division = $department
            ? Division::query()->where('department_id', $department->id)->orderBy('id')->first()
            : null;
        $position = Position::query()
            ->where('company_id', $company->id)
            ->when($department, fn ($query) => $query->where('department_id', $department->id))
            ->when($division, fn ($query) => $query->where('division_id', $division->id))
            ->orderBy('id')
            ->first();

        $employee = Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-T-%03d', $sequence),
            'full_name' => "Test Employee {$sequence}",
            'first_name' => 'Test',
            'last_name' => "Employee {$sequence}",
            'email' => sprintf('test-employee-%03d@example.test', $sequence),
            'phone' => sprintf('08123000%04d', $sequence),
            'company_id' => $company->id,
            'company_group_id' => $company->company_group_id,
            'branch_id' => $branch?->id,
            'work_location_id' => $workLocation?->id,
            'department_id' => $department?->id,
            'division_id' => $division?->id,
            'position_id' => $position?->id,
            'job_level_id' => JobLevel::query()->where('code', 'L1')->value('id'),
            'job_grade_id' => JobGrade::query()->where('code', 'G1')->value('id'),
            'identity_type_id' => IdentityType::query()->where('code', 'KTP')->value('id'),
            'nik_ktp' => sprintf('887401010190%04d', $sequence),
            'employment_status_id' => EmploymentStatus::query()->where('code', 'ACTIVE')->value('id'),
            'employment_type_id' => EmploymentType::query()->where('code', 'PKWTT')->value('id'),
            'contract_type_id' => ContractType::query()->where('code', 'PERMANENT')->value('id'),
            'religion_id' => Religion::query()->where('code', 'ISLAM')->value('id'),
            'marital_status_id' => MaritalStatus::query()->where('code', 'SINGLE')->value('id'),
            'join_date' => now()->toDateString(),
            'hire_date' => now()->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->assignRole($role);

        return $employee;
    }
}
