<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payroll;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TenancySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenancyFoundationTest extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_default_company_seed_data_is_created(): void
    {
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->first();

        $this->assertNotNull($company);
        $this->assertDatabaseHas('company_groups', ['code' => CompanyGroup::DEFAULT_CODE]);
        $this->assertNotNull($company->company_group_id);
        $this->assertDatabaseHas('companies', ['code' => 'SUB-A']);
        $this->assertDatabaseHas('companies', ['code' => 'SUB-B']);
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'code' => 'HQ']);
        $this->assertDatabaseHas('work_locations', ['company_id' => $company->id, 'code' => 'HQ-LOC']);
        $this->assertDatabaseHas('cost_centers', ['company_id' => $company->id, 'code' => 'GENERAL']);
        $this->assertDatabaseHas('subscription_plans', ['code' => 'STARTER']);
        $this->assertDatabaseHas('company_subscriptions', ['company_id' => $company->id, 'status' => 'active']);
    }

    public function test_existing_employee_records_are_assigned_company_id(): void
    {
        DB::table('employees')->insert([
            'employee_code' => 'LEGACY-001',
            'first_name' => 'Legacy',
            'last_name' => 'Employee',
            'email' => 'legacy@example.com',
            'employment_type' => 'Permanent',
            'hire_date' => now()->toDateString(),
            'is_active' => true,
            'password' => bcrypt('password123'),
            'company_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(TenancySeeder::class);

        $legacyEmployee = Employee::query()->where('email', 'legacy@example.com')->firstOrFail();

        $this->assertSame(Company::getDefaultCompanyId(), $legacyEmployee->company_id);
    }

    public function test_admin_cannot_access_another_companys_employee(): void
    {
        [$companyA, $companyB] = $this->makeCompanies();
        $admin = $this->makeEmployee('admin', ['company_id' => $companyA->id]);
        $otherEmployee = $this->makeEmployee('employee', ['company_id' => $companyB->id]);

        $this->assertFalse(Gate::forUser($admin)->allows('view', $otherEmployee));
    }

    public function test_finance_cannot_access_another_companys_payroll(): void
    {
        [$companyA, $companyB] = $this->makeCompanies();
        $finance = $this->makeEmployee('finance', ['company_id' => $companyA->id]);
        $otherEmployee = $this->makeEmployee('employee', ['company_id' => $companyB->id]);
        $payroll = Payroll::create([
            'company_id' => $companyB->id,
            'employee_id' => $otherEmployee->id,
            'pay_date' => now()->toDateString(),
            'period' => '2026-06',
            'gross_pay' => 1000,
            'net_pay' => 900,
            'status' => 'pending',
        ]);

        $this->assertFalse(Gate::forUser($finance)->allows('view', $payroll));
    }

    public function test_employee_cannot_access_another_companys_record(): void
    {
        [$companyA, $companyB] = $this->makeCompanies();
        $employee = $this->makeEmployee('employee', ['company_id' => $companyA->id]);
        $otherEmployee = $this->makeEmployee('employee', ['company_id' => $companyB->id]);
        $leave = Leave::create([
            'company_id' => $companyB->id,
            'employee_id' => $otherEmployee->id,
            'leave_type' => 'Vacation',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'Pending',
        ]);

        $this->assertFalse(Gate::forUser($employee)->allows('view', $leave));
    }

    public function test_super_admin_can_access_all_company_records(): void
    {
        [, $companyB] = $this->makeCompanies();
        $superAdmin = $this->makeEmployee('super_admin');
        $otherEmployee = $this->makeEmployee('employee', ['company_id' => $companyB->id]);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $otherEmployee));
    }

    public function test_company_scoped_query_works(): void
    {
        [$companyA, $companyB] = $this->makeCompanies();
        $employeeA = $this->makeEmployee('employee', ['company_id' => $companyA->id]);
        $employeeB = $this->makeEmployee('employee', ['company_id' => $companyB->id]);

        $scopedIds = Employee::query()->forCompany($companyA->id)->pluck('id')->all();

        $this->assertContains($employeeA->id, $scopedIds);
        $this->assertNotContains($employeeB->id, $scopedIds);
    }

    public function test_migrate_fresh_seed_works(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true])->assertExitCode(0);

        $this->assertDatabaseHas('companies', ['code' => Company::DEFAULT_CODE]);
    }

    private function makeCompanies(): array
    {
        $companyA = Company::query()->firstOrCreate(
            ['code' => 'COMP-A'],
            ['name' => 'Company A', 'is_active' => true]
        );
        $companyB = Company::query()->firstOrCreate(
            ['code' => 'COMP-B'],
            ['name' => 'Company B', 'is_active' => true]
        );

        return [$companyA, $companyB];
    }

    private function makeEmployee(string $role, array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;

        $employee = Employee::create(array_merge([
            'employee_code' => sprintf('EMP-T-%03d', $sequence),
            'first_name' => 'Tenancy',
            'last_name' => "User {$sequence}",
            'email' => sprintf('tenancy-%03d@example.com', $sequence),
            'employment_type' => 'Permanent',
            'hire_date' => now()->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->assignRole($role);

        return $employee;
    }
}
