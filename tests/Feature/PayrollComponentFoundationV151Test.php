<?php

namespace Tests\Feature;

use App\Models\AttendancePayrollSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Services\PayrollRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollComponentFoundationV151Test extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_payroll_component_can_be_created_with_flags_and_applicability_metadata(): void
    {
        $component = PayrollComponent::factory()->create([
            'company_id' => $this->defaultCompany()->id,
            'component_code' => 'BASIC_PAY',
            'name' => 'Basic Pay',
            'component_type' => PayrollComponent::TYPE_EARNING,
            'value_type' => PayrollComponent::VALUE_TYPE_FIXED,
            'default_amount' => '12500000.00',
            'taxable' => true,
            'tax_deductible' => false,
            'bpjs_applicable' => true,
            'thr_applicable' => true,
            'proratable' => true,
            'recurring' => true,
            'metadata' => [
                'applicability' => [
                    'employment_status_codes' => ['ACTIVE', 'PROBATION'],
                    'employment_type_codes' => ['PKWTT', 'PKWT', 'DAILY_WORKER', 'INTERN', 'EXPATRIATE'],
                    'contract_type_codes' => ['PERMANENT', 'FIXED_TERM', 'INTERNSHIP_AGREEMENT'],
                    'payroll_schemes' => ['monthly', 'split_payroll'],
                    'employee_groups' => ['production_manpower', 'office_staff'],
                    'expatriate_applicable' => true,
                    'daily_worker_applicable' => true,
                    'intern_applicable' => true,
                    'probation_applicable' => true,
                    'part_time_applicable' => true,
                ],
            ],
        ]);

        $component->refresh();

        $this->assertDatabaseHas('payroll_components', [
            'id' => $component->id,
            'company_id' => $this->defaultCompany()->id,
            'component_code' => 'BASIC_PAY',
            'component_type' => PayrollComponent::TYPE_EARNING,
            'value_type' => PayrollComponent::VALUE_TYPE_FIXED,
            'taxable' => true,
            'bpjs_applicable' => true,
            'thr_applicable' => true,
            'proratable' => true,
            'recurring' => true,
            'active' => true,
        ]);

        $this->assertSame(
            ['ACTIVE', 'PROBATION'],
            data_get($component->metadata, 'applicability.employment_status_codes')
        );
        $this->assertSame(
            ['PKWTT', 'PKWT', 'DAILY_WORKER', 'INTERN', 'EXPATRIATE'],
            data_get($component->metadata, 'applicability.employment_type_codes')
        );
        $this->assertSame(
            ['PERMANENT', 'FIXED_TERM', 'INTERNSHIP_AGREEMENT'],
            data_get($component->metadata, 'applicability.contract_type_codes')
        );
        $this->assertSame(
            ['monthly', 'split_payroll'],
            data_get($component->metadata, 'applicability.payroll_schemes')
        );
        $this->assertSame(
            ['production_manpower', 'office_staff'],
            data_get($component->metadata, 'applicability.employee_groups')
        );
        $this->assertTrue((bool) data_get($component->metadata, 'applicability.expatriate_applicable'));
        $this->assertTrue((bool) data_get($component->metadata, 'applicability.daily_worker_applicable'));
        $this->assertTrue((bool) data_get($component->metadata, 'applicability.intern_applicable'));
        $this->assertTrue((bool) data_get($component->metadata, 'applicability.probation_applicable'));
        $this->assertTrue((bool) data_get($component->metadata, 'applicability.part_time_applicable'));
    }

    public function test_component_code_uniqueness_is_enforced_per_company_but_can_repeat_in_a_different_company(): void
    {
        PayrollComponent::factory()->create([
            'company_id' => $this->defaultCompany()->id,
            'component_code' => 'TRANSPORT_ALLOWANCE',
        ]);

        try {
            PayrollComponent::factory()->create([
                'company_id' => $this->defaultCompany()->id,
                'component_code' => 'TRANSPORT_ALLOWANCE',
            ]);
            $this->fail('A duplicate component code should not be allowed in the same company.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('component_code', $exception->errors());
        }

        $otherCompanyComponent = PayrollComponent::factory()->create([
            'company_id' => $this->secondaryCompany()->id,
            'component_code' => 'TRANSPORT_ALLOWANCE',
        ]);

        $this->assertSame($this->secondaryCompany()->id, $otherCompanyComponent->company_id);
    }

    public function test_component_type_must_be_valid(): void
    {
        $this->expectException(ValidationException::class);

        PayrollComponent::factory()->create([
            'company_id' => $this->defaultCompany()->id,
            'component_type' => 'invalid_type',
        ]);
    }

    public function test_value_type_must_be_valid(): void
    {
        $this->expectException(ValidationException::class);

        PayrollComponent::factory()->create([
            'company_id' => $this->defaultCompany()->id,
            'value_type' => 'invalid_value_type',
        ]);
    }

    public function test_component_can_be_activated_and_deactivated(): void
    {
        $component = PayrollComponent::factory()->create([
            'company_id' => $this->defaultCompany()->id,
            'active' => true,
        ]);

        $component->update(['active' => false]);
        $this->assertFalse($component->fresh()->active);

        $component->update(['active' => true]);
        $this->assertTrue($component->fresh()->active);
    }

    public function test_all_supported_component_classifications_can_be_stored(): void
    {
        $types = [
            PayrollComponent::TYPE_EARNING,
            PayrollComponent::TYPE_DEDUCTION,
            PayrollComponent::TYPE_BENEFIT,
            PayrollComponent::TYPE_TAX,
            PayrollComponent::TYPE_EMPLOYER_CONTRIBUTION,
            PayrollComponent::TYPE_INFORMATIONAL,
        ];

        foreach ($types as $index => $type) {
            PayrollComponent::factory()->create([
                'company_id' => $this->defaultCompany()->id,
                'component_code' => sprintf('TYPE_%02d', $index + 1),
                'component_type' => $type,
            ]);
        }

        $this->assertSame(
            $types,
            PayrollComponent::query()
                ->where('company_id', $this->defaultCompany()->id)
                ->whereIn('component_code', ['TYPE_01', 'TYPE_02', 'TYPE_03', 'TYPE_04', 'TYPE_05', 'TYPE_06'])
                ->orderBy('component_code')
                ->pluck('component_type')
                ->all()
        );
    }

    public function test_percentage_and_formula_placeholder_configuration_rules_are_enforced(): void
    {
        try {
            PayrollComponent::factory()->create([
                'company_id' => $this->defaultCompany()->id,
                'value_type' => PayrollComponent::VALUE_TYPE_PERCENTAGE,
                'default_amount' => null,
                'default_percentage' => null,
            ]);
            $this->fail('Percentage components should require a percentage configuration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('default_percentage', $exception->errors());
        }

        try {
            PayrollComponent::factory()->create([
                'company_id' => $this->defaultCompany()->id,
                'value_type' => PayrollComponent::VALUE_TYPE_FORMULA_PLACEHOLDER,
                'default_amount' => '500000.00',
                'default_percentage' => null,
            ]);
            $this->fail('Formula placeholder components should not store calculation defaults.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value_type', $exception->errors());
        }
    }

    public function test_no_employee_assignment_or_payroll_calculation_tables_are_added(): void
    {
        $this->assertFalse(Schema::hasTable('employee_payroll_profiles'));
        $this->assertFalse(Schema::hasTable('employee_salary_components'));
        $this->assertFalse(Schema::hasTable('employee_payroll_components'));

        foreach ([
            'employee_id',
            'gross_pay',
            'net_pay',
            'take_home_pay',
            'final_amount',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('payroll_components', $column), "Unexpected payroll_components column [{$column}] found.");
        }
    }

    public function test_payroll_run_and_employee_foundation_remains_unchanged_after_component_creation(): void
    {
        PayrollComponent::factory()->create([
            'company_id' => $this->defaultCompany()->id,
            'component_code' => 'UNCHANGED_RUN_SUPPORT',
            'value_type' => PayrollComponent::VALUE_TYPE_MANUAL,
        ]);

        $service = app(PayrollRunService::class);
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');
        $employee = $this->makeEmployee('employee');
        $snapshot = $this->createSnapshot($employee, $period, AttendancePayrollSnapshot::STATUS_CALCULATED);
        $run = $service->createRun(payrollPeriod: $period, actor: $finance);
        $preparedRun = $service->prepareRun(payrollRun: $run, employeeIds: [$employee->id], actor: $finance);

        $runEmployee = PayrollRunEmployee::query()->where('payroll_run_id', $preparedRun->id)->firstOrFail();

        $this->assertSame(PayrollRun::STATUS_PREPARED, $preparedRun->status);
        $this->assertSame(1, $preparedRun->total_employees);
        $this->assertSame(1, $preparedRun->ready_employees);
        $this->assertSame(0, $preparedRun->blocked_employees);
        $this->assertSame($snapshot->id, $runEmployee->attendance_payroll_snapshot_id);
        $this->assertSame(PayrollRunEmployee::STATUS_READY, $runEmployee->status);
    }

    private function defaultCompany(): Company
    {
        return Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
    }

    private function secondaryCompany(): Company
    {
        return Company::query()->where('code', 'SUB-A')->firstOrFail();
    }

    private function createPayrollPeriod(): PayrollPeriod
    {
        $sequence = $this->sequence++;

        return PayrollPeriod::query()->create([
            'company_id' => $this->defaultCompany()->id,
            'period_code' => sprintf('PPC-%03d', $sequence),
            'name' => "Payroll Component Period {$sequence}",
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'pay_date' => '2026-08-01',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    private function createSnapshot(
        Employee $employee,
        PayrollPeriod $period,
        string $status,
    ): AttendancePayrollSnapshot {
        return AttendancePayrollSnapshot::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'total_work_days' => 22,
            'total_present_days' => 21,
            'total_absent_days' => 1,
            'total_late_minutes' => 0,
            'total_early_leave_minutes' => 0,
            'total_work_minutes' => 10080,
            'total_overtime_minutes' => 60,
            'total_leave_days' => '0.00',
            'total_correction_count' => 0,
            'snapshot_status' => $status,
            'calculated_at' => now(config('app.timezone'))->subHour(),
            'metadata' => [
                'generated_by' => self::class,
            ],
        ]);
    }

    private function makeEmployee(string $role): Employee
    {
        $sequence = $this->sequence++;
        $company = $this->defaultCompany();

        $employee = Employee::query()->create([
            'employee_code' => sprintf('EMP-COMP-%03d', $sequence),
            'full_name' => "Payroll Component User {$sequence}",
            'first_name' => 'Payroll',
            'last_name' => "Component {$sequence}",
            'email' => sprintf('payroll-component-user-%03d@example.test', $sequence),
            'company_id' => $company->id,
            'employment_type' => 'Permanent',
            'hire_date' => now(config('app.timezone'))->toDateString(),
            'join_date' => now(config('app.timezone'))->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ]);

        $employee->assignRole($role);

        return $employee;
    }
}
