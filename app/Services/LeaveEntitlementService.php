<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveEntitlementService
{
    public function __construct(
        private readonly LeaveBalanceService $leaveBalanceService,
    ) {
    }

    public function generateEntitlement(Employee $employee, LeaveType $leaveType, int $year): LeaveEntitlement
    {
        $this->assertSharedCompanyScope((int) $employee->company_id, (int) $leaveType->company_id);

        return DB::transaction(function () use ($employee, $leaveType, $year): LeaveEntitlement {
            $existing = LeaveEntitlement::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->getKey())
                ->where('leave_type_id', $leaveType->getKey())
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof LeaveEntitlement) {
                return $existing->loadMissing(['employee', 'leaveType', 'transactions']);
            }

            $referenceDate = $this->referenceDateForYear($year);
            $policy = $this->findApplicablePolicy($employee, $leaveType, $referenceDate);

            if (! $policy instanceof LeavePolicy) {
                throw new RuntimeException("No applicable leave policy was found for leave type [{$leaveType->code}] in year [{$year}].");
            }

            $entitledDays = (float) $policy->entitlement_days;

            $entitlement = LeaveEntitlement::query()->create([
                'company_id' => $employee->company_id ?: Company::getDefaultCompanyId(),
                'employee_id' => $employee->getKey(),
                'leave_type_id' => $leaveType->getKey(),
                'year' => $year,
                'entitled_days' => $entitledDays,
                'carried_forward_days' => 0,
                'used_days' => 0,
                'remaining_days' => $entitledDays,
            ]);

            $this->leaveBalanceService->recordTransaction(
                $entitlement,
                LeaveTransaction::TYPE_ENTITLEMENT,
                $entitledDays,
                0,
                $entitledDays,
                LeavePolicy::class,
                $policy->getKey(),
                'Entitlement generated from active leave policy.',
                null,
            );

            return $entitlement->loadMissing(['employee', 'leaveType', 'transactions']);
        });
    }

    public function generateAnnualEntitlements(int $year, ?int $companyId = null): int
    {
        $createdCount = 0;
        $referenceDate = $this->referenceDateForYear($year);

        Employee::query()
            ->where('is_active', true)
            ->when(filled($companyId), fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('id')
            ->get()
            ->each(function (Employee $employee) use ($year, $referenceDate, &$createdCount): void {
                LeaveType::query()
                    ->active()
                    ->forCompany($employee->company_id)
                    ->orderBy('name')
                    ->get()
                    ->each(function (LeaveType $leaveType) use ($employee, $year, $referenceDate, &$createdCount): void {
                        if (! $this->findApplicablePolicy($employee, $leaveType, $referenceDate)) {
                            return;
                        }

                        $existing = LeaveEntitlement::query()
                            ->where('company_id', $employee->company_id)
                            ->where('employee_id', $employee->getKey())
                            ->where('leave_type_id', $leaveType->getKey())
                            ->where('year', $year)
                            ->exists();

                        $entitlement = $this->generateEntitlement($employee, $leaveType, $year);

                        if (! $existing && $entitlement->exists) {
                            $createdCount++;
                        }
                    });
            });

        LeaveEntitlement::query()
            ->with(['employee', 'leaveType'])
            ->where('year', $year - 1)
            ->where('remaining_days', '>', 0)
            ->when(filled($companyId), fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('id')
            ->get()
            ->each(fn (LeaveEntitlement $entitlement) => $this->applyCarryForward($entitlement, $year));

        return $createdCount;
    }

    public function findApplicablePolicy(Employee $employee, LeaveType $leaveType, ?Carbon $date = null): ?LeavePolicy
    {
        $this->assertSharedCompanyScope((int) $employee->company_id, (int) $leaveType->company_id);

        $effectiveDate = ($date ?: now('Asia/Jakarta'))->copy()->startOfDay();

        $policy = LeavePolicy::query()
            ->where('company_id', $employee->company_id)
            ->where('leave_type_id', $leaveType->getKey())
            ->active()
            ->whereDate('effective_from', '<=', $effectiveDate->toDateString())
            ->where(function ($query) use ($effectiveDate): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $effectiveDate->toDateString());
            })
            ->where(function ($query) use ($employee): void {
                $query->whereNull('employment_status_id')
                    ->orWhere('employment_status_id', $employee->employment_status_id);
            })
            ->where(function ($query) use ($employee): void {
                $query->whereNull('job_level_id')
                    ->orWhere('job_level_id', $employee->job_level_id);
            })
            ->orderByRaw('case when employment_status_id = ? then 1 else 0 end desc', [$employee->employment_status_id])
            ->orderByRaw('case when job_level_id = ? then 1 else 0 end desc', [$employee->job_level_id])
            ->orderByDesc('effective_from')
            ->first();

        if (! $policy instanceof LeavePolicy) {
            return null;
        }

        $joinDate = $employee->join_date ?: $employee->hire_date;

        if (filled($joinDate) && $policy->minimum_service_months > 0) {
            $serviceMonths = Carbon::parse($joinDate)->startOfDay()->diffInMonths($effectiveDate);

            if ($serviceMonths < $policy->minimum_service_months) {
                return null;
            }
        }

        return $policy;
    }

    public function applyCarryForward(LeaveEntitlement $previousYearEntitlement, int $targetYear): ?LeaveEntitlement
    {
        $previousYearEntitlement->loadMissing(['employee', 'leaveType']);

        $leaveType = $previousYearEntitlement->leaveType;
        $employee = $previousYearEntitlement->employee;

        if (! $leaveType instanceof LeaveType || ! $employee instanceof Employee || ! $leaveType->allow_carry_forward) {
            return null;
        }

        $carryForwardDays = min(
            max((float) $previousYearEntitlement->remaining_days, 0),
            max((float) ($leaveType->max_carry_forward_days ?? 0), 0),
        );

        if ($carryForwardDays <= 0) {
            return null;
        }

        return DB::transaction(function () use ($previousYearEntitlement, $employee, $leaveType, $targetYear, $carryForwardDays): ?LeaveEntitlement {
            $policy = $this->findApplicablePolicy($employee, $leaveType, $this->referenceDateForYear($targetYear));
            $targetEntitlement = LeaveEntitlement::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->getKey())
                ->where('leave_type_id', $leaveType->getKey())
                ->where('year', $targetYear)
                ->lockForUpdate()
                ->first();

            if (! $targetEntitlement instanceof LeaveEntitlement) {
                $entitledDays = $policy instanceof LeavePolicy ? (float) $policy->entitlement_days : 0;

                $targetEntitlement = LeaveEntitlement::query()->create([
                    'company_id' => $employee->company_id ?: Company::getDefaultCompanyId(),
                    'employee_id' => $employee->getKey(),
                    'leave_type_id' => $leaveType->getKey(),
                    'year' => $targetYear,
                    'entitled_days' => $entitledDays,
                    'carried_forward_days' => 0,
                    'used_days' => 0,
                    'remaining_days' => $entitledDays,
                    'expires_at' => Carbon::create($targetYear, 12, 31, 0, 0, 0, 'Asia/Jakarta')->toDateString(),
                ]);

                if ($policy instanceof LeavePolicy) {
                    $this->leaveBalanceService->recordTransaction(
                        $targetEntitlement,
                        LeaveTransaction::TYPE_ENTITLEMENT,
                        $entitledDays,
                        0,
                        $entitledDays,
                        LeavePolicy::class,
                        $policy->getKey(),
                        'Entitlement generated during carry forward processing.',
                        null,
                    );
                }
            }

            $carryForwardAlreadyApplied = $targetEntitlement->transactions()
                ->where('transaction_type', LeaveTransaction::TYPE_CARRY_FORWARD)
                ->where('reference_type', LeaveEntitlement::class)
                ->where('reference_id', $previousYearEntitlement->getKey())
                ->exists();

            if ($carryForwardAlreadyApplied) {
                return $targetEntitlement->loadMissing(['employee', 'leaveType', 'transactions']);
            }

            $balanceBefore = (float) $targetEntitlement->remaining_days;
            $balanceAfter = $balanceBefore + $carryForwardDays;

            $targetEntitlement->forceFill([
                'carried_forward_days' => (float) $targetEntitlement->carried_forward_days + $carryForwardDays,
                'remaining_days' => $balanceAfter,
                'expires_at' => $targetEntitlement->expires_at
                    ?: Carbon::create($targetYear, 12, 31, 0, 0, 0, 'Asia/Jakarta')->toDateString(),
            ])->save();

            $this->leaveBalanceService->recordTransaction(
                $targetEntitlement,
                LeaveTransaction::TYPE_CARRY_FORWARD,
                $carryForwardDays,
                $balanceBefore,
                $balanceAfter,
                LeaveEntitlement::class,
                $previousYearEntitlement->getKey(),
                "Carry forward applied from entitlement year {$previousYearEntitlement->year}.",
                null,
            );

            return $targetEntitlement->loadMissing(['employee', 'leaveType', 'transactions']);
        });
    }

    private function referenceDateForYear(int $year): Carbon
    {
        $today = now('Asia/Jakarta')->startOfDay();
        $currentYear = (int) $today->year;

        if ($year < $currentYear) {
            return Carbon::create($year, 12, 31, 0, 0, 0, 'Asia/Jakarta');
        }

        if ($year > $currentYear) {
            return Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta');
        }

        return $today;
    }

    private function assertSharedCompanyScope(int $leftCompanyId, int $rightCompanyId): void
    {
        if ($leftCompanyId !== $rightCompanyId) {
            throw new RuntimeException('Leave entitlement generation cannot cross company boundaries.');
        }
    }
}
