<?php

namespace Tests\Feature;

use App\Models\AttendancePayrollSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Services\PayrollRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollPeriodAndRunFoundationV150Test extends TestCase
{
    use RefreshDatabase;

    private PayrollRunService $service;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->service = app(PayrollRunService::class);
    }

    public function test_payroll_period_can_be_created(): void
    {
        $company = $this->defaultCompany();

        $period = PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_code' => '2026-07-A',
            'name' => 'July 2026 Payroll Period A',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'pay_date' => '2026-08-01',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('payroll_periods', [
            'id' => $period->id,
            'company_id' => $company->id,
            'name' => 'July 2026 Payroll Period A',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    public function test_invalid_payroll_period_date_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        PayrollPeriod::query()->create([
            'company_id' => $this->defaultCompany()->id,
            'name' => 'Invalid Payroll Period',
            'period_start' => '2026-07-31',
            'period_end' => '2026-07-01',
            'status' => PayrollPeriod::STATUS_DRAFT,
        ]);
    }

    public function test_overlapping_non_cancelled_payroll_periods_are_prevented(): void
    {
        $company = $this->defaultCompany();

        PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'name' => 'July 2026 Main Period',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->expectException(ValidationException::class);

        PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'name' => 'July 2026 Overlap Period',
            'period_start' => '2026-07-15',
            'period_end' => '2026-08-15',
            'status' => PayrollPeriod::STATUS_DRAFT,
        ]);
    }

    public function test_payroll_run_can_be_created_from_period_and_copies_dates(): void
    {
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');

        $run = $this->service->createRun(
            payrollPeriod: $period,
            runType: PayrollRun::RUN_TYPE_REGULAR,
            actor: $finance,
        );

        $this->assertSame($period->company_id, $run->company_id);
        $this->assertSame($period->id, $run->payroll_period_id);
        $this->assertSame($period->period_start->toDateString(), $run->period_start->toDateString());
        $this->assertSame($period->period_end->toDateString(), $run->period_end->toDateString());
        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->status);
    }

    public function test_duplicate_active_regular_payroll_run_is_prevented_while_correction_and_off_cycle_runs_are_allowed(): void
    {
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');

        $regularRun = $this->service->createRun(
            payrollPeriod: $period,
            runType: PayrollRun::RUN_TYPE_REGULAR,
            actor: $finance,
        );

        $this->assertSame(PayrollRun::RUN_TYPE_REGULAR, $regularRun->run_type);

        try {
            $this->service->createRun(
                payrollPeriod: $period,
                runType: PayrollRun::RUN_TYPE_REGULAR,
                actor: $finance,
            );
            $this->fail('A duplicate active regular payroll run should not be created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('run_type', $exception->errors());
        }

        $correctionRun = $this->service->createRun(
            payrollPeriod: $period,
            runType: PayrollRun::RUN_TYPE_CORRECTION,
            actor: $finance,
        );
        $offCycleRun = $this->service->createRun(
            payrollPeriod: $period,
            runType: PayrollRun::RUN_TYPE_OFF_CYCLE,
            actor: $finance,
        );

        $this->assertSame(PayrollRun::RUN_TYPE_CORRECTION, $correctionRun->run_type);
        $this->assertSame(PayrollRun::RUN_TYPE_OFF_CYCLE, $offCycleRun->run_type);
        $this->assertSame(3, PayrollRun::query()->count());
    }

    public function test_payroll_run_can_include_selected_employees_link_snapshots_and_mark_calculated_or_locked_snapshots_ready(): void
    {
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');
        $calculatedEmployee = $this->makeEmployee('employee');
        $lockedEmployee = $this->makeEmployee('employee');
        $calculatedSnapshot = $this->createSnapshot($calculatedEmployee, $period, AttendancePayrollSnapshot::STATUS_CALCULATED);
        $lockedSnapshot = $this->createSnapshot($lockedEmployee, $period, AttendancePayrollSnapshot::STATUS_LOCKED, $finance);
        $run = $this->service->createRun(payrollPeriod: $period, actor: $finance);

        $preparedRun = $this->service->prepareRun(
            payrollRun: $run,
            employeeIds: [$calculatedEmployee->id, $lockedEmployee->id],
            actor: $finance,
        );

        $runEmployees = PayrollRunEmployee::query()
            ->where('payroll_run_id', $preparedRun->id)
            ->orderBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $this->assertCount(2, $runEmployees);
        $this->assertSame($calculatedSnapshot->id, $runEmployees[$calculatedEmployee->id]->attendance_payroll_snapshot_id);
        $this->assertSame($lockedSnapshot->id, $runEmployees[$lockedEmployee->id]->attendance_payroll_snapshot_id);
        $this->assertSame(PayrollRunEmployee::STATUS_READY, $runEmployees[$calculatedEmployee->id]->status);
        $this->assertSame(PayrollRunEmployee::STATUS_READY, $runEmployees[$lockedEmployee->id]->status);
        $this->assertSame(2, $preparedRun->total_employees);
        $this->assertSame(2, $preparedRun->ready_employees);
        $this->assertSame(0, $preparedRun->blocked_employees);
    }

    public function test_missing_stale_and_cancelled_snapshots_are_blocked_and_locking_requires_no_blocked_employees(): void
    {
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');
        $missingEmployee = $this->makeEmployee('employee');
        $staleEmployee = $this->makeEmployee('employee');
        $cancelledEmployee = $this->makeEmployee('employee');
        $run = $this->service->createRun(payrollPeriod: $period, actor: $finance);

        $this->createSnapshot($staleEmployee, $period, AttendancePayrollSnapshot::STATUS_STALE);
        $this->createSnapshot($cancelledEmployee, $period, AttendancePayrollSnapshot::STATUS_CANCELLED);

        $preparedRun = $this->service->prepareRun(
            payrollRun: $run,
            employeeIds: [$missingEmployee->id, $staleEmployee->id, $cancelledEmployee->id],
            actor: $finance,
        );

        $runEmployees = PayrollRunEmployee::query()
            ->where('payroll_run_id', $preparedRun->id)
            ->orderBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $this->assertSame(PayrollRunEmployee::STATUS_BLOCKED, $runEmployees[$missingEmployee->id]->status);
        $this->assertStringContainsString('missing', strtolower((string) $runEmployees[$missingEmployee->id]->readiness_message));
        $this->assertSame(PayrollRunEmployee::STATUS_BLOCKED, $runEmployees[$staleEmployee->id]->status);
        $this->assertStringContainsString('stale', strtolower((string) $runEmployees[$staleEmployee->id]->readiness_message));
        $this->assertSame(PayrollRunEmployee::STATUS_BLOCKED, $runEmployees[$cancelledEmployee->id]->status);
        $this->assertStringContainsString('cancelled', strtolower((string) $runEmployees[$cancelledEmployee->id]->readiness_message));
        $this->assertSame(3, $preparedRun->total_employees);
        $this->assertSame(0, $preparedRun->ready_employees);
        $this->assertSame(3, $preparedRun->blocked_employees);

        $this->expectException(ValidationException::class);

        $this->service->lockRun($preparedRun, $finance);
    }

    public function test_payroll_run_preparation_is_idempotent_and_does_not_duplicate_employees(): void
    {
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');
        $employeeA = $this->makeEmployee('employee');
        $employeeB = $this->makeEmployee('employee');
        $this->createSnapshot($employeeA, $period, AttendancePayrollSnapshot::STATUS_CALCULATED);
        $this->createSnapshot($employeeB, $period, AttendancePayrollSnapshot::STATUS_CALCULATED);
        $run = $this->service->createRun(payrollPeriod: $period, actor: $finance);

        $firstPreparation = $this->service->prepareRun(
            payrollRun: $run,
            employeeIds: [$employeeA->id],
            actor: $finance,
        );
        $secondPreparation = $this->service->prepareRun(
            payrollRun: $firstPreparation,
            employeeIds: [$employeeA->id, $employeeB->id],
            actor: $finance,
        );

        $this->assertSame(2, PayrollRunEmployee::query()->where('payroll_run_id', $run->id)->count());
        $this->assertSame(2, $secondPreparation->total_employees);
        $this->assertSame(2, $secondPreparation->ready_employees);
        $this->assertSame(0, $secondPreparation->blocked_employees);
    }

    public function test_locked_payroll_run_cannot_be_reprepared_directly(): void
    {
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');
        $employee = $this->makeEmployee('employee');
        $this->createSnapshot($employee, $period, AttendancePayrollSnapshot::STATUS_LOCKED, $finance);
        $run = $this->service->createRun(payrollPeriod: $period, actor: $finance);
        $preparedRun = $this->service->prepareRun(payrollRun: $run, employeeIds: [$employee->id], actor: $finance);
        $lockedRun = $this->service->lockRun($preparedRun, $finance);

        $this->assertSame(PayrollRun::STATUS_LOCKED, $lockedRun->status);
        $this->assertNotNull($lockedRun->locked_at);
        $this->assertSame($finance->id, $lockedRun->locked_by);

        $this->expectException(ValidationException::class);

        $this->service->prepareRun(payrollRun: $lockedRun, employeeIds: [$employee->id], actor: $finance);
    }

    public function test_approved_payroll_run_cannot_be_modified(): void
    {
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');
        $employee = $this->makeEmployee('employee');
        $this->createSnapshot($employee, $period, AttendancePayrollSnapshot::STATUS_CALCULATED);
        $run = $this->service->createRun(payrollPeriod: $period, actor: $finance);
        $preparedRun = $this->service->prepareRun(payrollRun: $run, employeeIds: [$employee->id], actor: $finance);
        $lockedRun = $this->service->lockRun($preparedRun, $finance);
        $approvedRun = $this->service->approveRun($lockedRun, $finance);

        $this->assertSame(PayrollRun::STATUS_APPROVED, $approvedRun->status);
        $this->assertSame($finance->id, $approvedRun->approved_by);

        $this->expectException(ValidationException::class);

        $this->service->cancelRun($approvedRun, $finance, 'Should not be allowed after approval.');
    }

    public function test_cancelled_payroll_run_cannot_be_modified(): void
    {
        $period = $this->createPayrollPeriod();
        $finance = $this->makeEmployee('finance');
        $employee = $this->makeEmployee('employee');
        $this->createSnapshot($employee, $period, AttendancePayrollSnapshot::STATUS_CALCULATED);
        $run = $this->service->createRun(payrollPeriod: $period, actor: $finance);
        $preparedRun = $this->service->prepareRun(payrollRun: $run, employeeIds: [$employee->id], actor: $finance);
        $cancelledRun = $this->service->cancelRun($preparedRun, $finance, 'Run created in error.');

        $this->assertSame(PayrollRun::STATUS_CANCELLED, $cancelledRun->status);
        $this->assertSame('Run created in error.', $cancelledRun->cancellation_reason);

        $this->expectException(ValidationException::class);

        $this->service->prepareRun(payrollRun: $cancelledRun, employeeIds: [$employee->id], actor: $finance);
    }

    public function test_payroll_run_and_payroll_run_employee_store_no_monetary_columns(): void
    {
        $this->assertTrue(Schema::hasTable('payroll_runs'));
        $this->assertTrue(Schema::hasTable('payroll_run_employees'));

        foreach ([
            'salary',
            'allowances',
            'deductions',
            'tax',
            'bpjs',
            'thr',
            'gross_pay',
            'net_pay',
            'take_home_pay',
            'final_amount',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('payroll_runs', $column), "Unexpected payroll_runs monetary column [{$column}] found.");
            $this->assertFalse(Schema::hasColumn('payroll_run_employees', $column), "Unexpected payroll_run_employees monetary column [{$column}] found.");
        }
    }

    private function createPayrollPeriod(): PayrollPeriod
    {
        $sequence = $this->employeeSequence++;

        return PayrollPeriod::query()->create([
            'company_id' => $this->defaultCompany()->id,
            'period_code' => sprintf('PP-%03d', $sequence),
            'name' => "Payroll Period {$sequence}",
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
        ?Employee $lockedBy = null,
    ): AttendancePayrollSnapshot {
        return AttendancePayrollSnapshot::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'total_work_days' => 22,
            'total_present_days' => 20,
            'total_absent_days' => 2,
            'total_late_minutes' => 10,
            'total_early_leave_minutes' => 5,
            'total_work_minutes' => 9600,
            'total_overtime_minutes' => 120,
            'total_leave_days' => '1.00',
            'total_correction_count' => 1,
            'snapshot_status' => $status,
            'calculated_at' => now(config('app.timezone'))->subHour(),
            'locked_at' => $status === AttendancePayrollSnapshot::STATUS_LOCKED ? now(config('app.timezone'))->subMinutes(30) : null,
            'locked_by' => $status === AttendancePayrollSnapshot::STATUS_LOCKED ? $lockedBy?->id : null,
            'metadata' => [
                'generated_by' => self::class,
            ],
        ]);
    }

    private function defaultCompany(): Company
    {
        return Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
    }

    private function makeEmployee(string $role): Employee
    {
        $sequence = $this->employeeSequence++;
        $company = $this->defaultCompany();

        $employee = Employee::query()->create([
            'employee_code' => sprintf('EMP-PAY-%03d', $sequence),
            'full_name' => "Payroll User {$sequence}",
            'first_name' => 'Payroll',
            'last_name' => "User {$sequence}",
            'email' => sprintf('payroll-user-%03d@example.test', $sequence),
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
