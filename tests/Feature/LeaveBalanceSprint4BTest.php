<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use App\Services\LeaveEntitlementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveBalanceSprint4BTest extends TestCase
{
    use RefreshDatabase;

    private LeaveBalanceService $leaveBalanceService;

    private LeaveEntitlementService $leaveEntitlementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->leaveBalanceService = app(LeaveBalanceService::class);
        $this->leaveEntitlementService = app(LeaveEntitlementService::class);
    }

    public function test_leave_balance_tables_migrate_successfully(): void
    {
        $this->assertTrue(Schema::hasTable('leave_entitlements'));
        $this->assertTrue(Schema::hasTable('leave_transactions'));
    }

    public function test_annual_entitlement_generation_uses_applicable_policy(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $leaveType = $this->leaveType($employee->company_id, 'ANNUAL');
        $year = now('Asia/Jakarta')->year + 1;

        $entitlement = $this->leaveEntitlementService->generateEntitlement($employee, $leaveType, $year);

        $this->assertSame($employee->id, $entitlement->employee_id);
        $this->assertSame($leaveType->id, $entitlement->leave_type_id);
        $this->assertSame($year, $entitlement->year);
        $this->assertSame('12.00', $entitlement->entitled_days);
        $this->assertSame('12.00', $entitlement->remaining_days);
    }

    public function test_duplicate_entitlement_generation_returns_existing_record(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $leaveType = $this->leaveType($employee->company_id, 'ANNUAL');
        $year = now('Asia/Jakarta')->year + 1;

        $first = $this->leaveEntitlementService->generateEntitlement($employee, $leaveType, $year);
        $second = $this->leaveEntitlementService->generateEntitlement($employee, $leaveType, $year);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LeaveEntitlement::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->count());
    }

    public function test_entitlement_generation_creates_entitlement_transaction(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $leaveType = $this->leaveType($employee->company_id, 'ANNUAL');
        $year = now('Asia/Jakarta')->year + 1;

        $entitlement = $this->leaveEntitlementService->generateEntitlement($employee, $leaveType, $year);

        $transaction = $entitlement->transactions()->first();

        $this->assertInstanceOf(LeaveTransaction::class, $transaction);
        $this->assertSame(LeaveTransaction::TYPE_ENTITLEMENT, $transaction->transaction_type);
        $this->assertSame('12.00', $transaction->days);
    }

    public function test_deduct_balance_decreases_remaining_and_increases_used(): void
    {
        $entitlement = $this->currentYearEntitlement('andi.permanent@example.test', 'ANNUAL');

        $transaction = $this->leaveBalanceService->deductBalance($entitlement, 2, 'test', 101, 'Manual deduction test.');
        $entitlement = $entitlement->fresh();

        $this->assertSame('2.00', $entitlement->used_days);
        $this->assertSame('10.00', $entitlement->remaining_days);
        $this->assertSame(LeaveTransaction::TYPE_LEAVE_TAKEN, $transaction->transaction_type);
        $this->assertSame('12.00', $transaction->balance_before);
        $this->assertSame('10.00', $transaction->balance_after);
    }

    public function test_restore_balance_increases_remaining_and_decreases_used(): void
    {
        $entitlement = $this->currentYearEntitlement('andi.permanent@example.test', 'ANNUAL');

        $this->leaveBalanceService->deductBalance($entitlement, 3, 'test', 102, 'Deduct before restore.');
        $transaction = $this->leaveBalanceService->restoreBalance($entitlement->fresh(), 1, 'test', 103, 'Restore one day.');
        $entitlement = $entitlement->fresh();

        $this->assertSame('2.00', $entitlement->used_days);
        $this->assertSame('10.00', $entitlement->remaining_days);
        $this->assertSame(LeaveTransaction::TYPE_RESTORE, $transaction->transaction_type);
        $this->assertSame('9.00', $transaction->balance_before);
        $this->assertSame('10.00', $transaction->balance_after);
    }

    public function test_negative_balance_is_blocked_for_paid_leave(): void
    {
        $entitlement = $this->currentYearEntitlement('andi.permanent@example.test', 'ANNUAL');

        $this->expectException(ValidationException::class);

        $this->leaveBalanceService->deductBalance($entitlement, 20, 'test', 104, 'Should fail.');
    }

    public function test_unpaid_leave_can_go_below_zero_safely(): void
    {
        $entitlement = $this->currentYearEntitlement('andi.permanent@example.test', 'UNPAID');

        $transaction = $this->leaveBalanceService->deductBalance($entitlement, 2, 'test', 105, 'Unpaid leave deduction.');
        $entitlement = $entitlement->fresh();

        $this->assertSame('2.00', $entitlement->used_days);
        $this->assertSame('-2.00', $entitlement->remaining_days);
        $this->assertSame(LeaveTransaction::TYPE_LEAVE_TAKEN, $transaction->transaction_type);
    }

    public function test_carry_forward_respects_maximum_days(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $leaveType = $this->leaveType($employee->company_id, 'ANNUAL');
        $currentYear = now('Asia/Jakarta')->year;

        $previousYearEntitlement = LeaveEntitlement::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $currentYear - 1,
            'entitled_days' => 12,
            'carried_forward_days' => 0,
            'used_days' => 2,
            'remaining_days' => 10,
        ]);

        $targetEntitlement = $this->leaveEntitlementService->applyCarryForward($previousYearEntitlement, $currentYear + 1);

        $this->assertInstanceOf(LeaveEntitlement::class, $targetEntitlement);
        $this->assertSame('6.00', $targetEntitlement->carried_forward_days);
        $this->assertSame('18.00', $targetEntitlement->remaining_days);
        $this->assertTrue($targetEntitlement->transactions()
            ->where('transaction_type', LeaveTransaction::TYPE_CARRY_FORWARD)
            ->exists());
    }

    public function test_company_scoped_generation_only_creates_target_company_entitlements(): void
    {
        $companyA = $this->company(Company::DEFAULT_CODE);
        $companyB = $this->company('SUB-A');
        $year = now('Asia/Jakarta')->year + 1;

        $createdCount = $this->leaveEntitlementService->generateAnnualEntitlements($year, $companyA->id);

        $this->assertGreaterThan(0, $createdCount);
        $this->assertTrue(LeaveEntitlement::query()->forCompany($companyA->id)->where('year', $year)->exists());
        $this->assertFalse(LeaveEntitlement::query()->forCompany($companyB->id)->where('year', $year)->exists());
    }

    public function test_every_balance_mutation_creates_a_transaction(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $leaveType = $this->leaveType($employee->company_id, 'ANNUAL');
        $year = now('Asia/Jakarta')->year + 1;

        $entitlement = $this->leaveEntitlementService->generateEntitlement($employee, $leaveType, $year);

        $this->leaveBalanceService->deductBalance($entitlement, 2, 'test', 201, 'Deduct balance.');
        $this->leaveBalanceService->restoreBalance($entitlement->fresh(), 1, 'test', 202, 'Restore balance.');
        $this->leaveBalanceService->adjustBalance($entitlement->fresh(), -0.5, 'Admin adjustment.');

        $entitlement = $entitlement->fresh();

        $this->assertSame(4, $entitlement->transactions()->count());
        $this->assertSame('10.50', $entitlement->remaining_days);
    }

    private function company(string $code): Company
    {
        return Company::query()->where('code', $code)->firstOrFail();
    }

    private function employee(string $email): Employee
    {
        return Employee::query()->where('email', $email)->firstOrFail();
    }

    private function leaveType(int $companyId, string $code): LeaveType
    {
        return LeaveType::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function currentYearEntitlement(string $email, string $leaveTypeCode): LeaveEntitlement
    {
        $employee = $this->employee($email);
        $leaveType = $this->leaveType($employee->company_id, $leaveTypeCode);

        return LeaveEntitlement::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', now('Asia/Jakarta')->year)
            ->firstOrFail();
    }
}
