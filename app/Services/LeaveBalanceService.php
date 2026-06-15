<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LeaveBalanceService
{
    public function getBalance(Employee $employee, LeaveType $leaveType, int $year): ?LeaveEntitlement
    {
        $this->assertSharedCompanyScope((int) $employee->company_id, (int) $leaveType->company_id);

        return LeaveEntitlement::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->getKey())
            ->where('leave_type_id', $leaveType->getKey())
            ->where('year', $year)
            ->first();
    }

    public function deductBalance(
        LeaveEntitlement $entitlement,
        float $days,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null,
    ): LeaveTransaction {
        return DB::transaction(function () use ($entitlement, $days, $referenceType, $referenceId, $remarks): LeaveTransaction {
            $entitlement = $this->lockEntitlement($entitlement);
            $this->assertPositiveDays($days);

            $leaveType = $this->resolveLeaveType($entitlement);
            $balanceBefore = (float) $entitlement->remaining_days;
            $balanceAfter = $balanceBefore - $days;

            if ($leaveType->is_paid && $balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'days' => 'The requested leave exceeds the remaining paid leave balance.',
                ]);
            }

            $entitlement->forceFill([
                'used_days' => (float) $entitlement->used_days + $days,
                'remaining_days' => $balanceAfter,
            ])->save();

            return $this->recordTransaction(
                $entitlement,
                LeaveTransaction::TYPE_LEAVE_TAKEN,
                $days,
                $balanceBefore,
                $balanceAfter,
                $referenceType,
                $referenceId,
                $remarks,
            );
        });
    }

    public function restoreBalance(
        LeaveEntitlement $entitlement,
        float $days,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null,
    ): LeaveTransaction {
        return DB::transaction(function () use ($entitlement, $days, $referenceType, $referenceId, $remarks): LeaveTransaction {
            $entitlement = $this->lockEntitlement($entitlement);
            $this->assertPositiveDays($days);

            if ((float) $entitlement->used_days < $days) {
                throw ValidationException::withMessages([
                    'days' => 'The restore days cannot exceed the used leave days.',
                ]);
            }

            $balanceBefore = (float) $entitlement->remaining_days;
            $balanceAfter = $balanceBefore + $days;

            $entitlement->forceFill([
                'used_days' => (float) $entitlement->used_days - $days,
                'remaining_days' => $balanceAfter,
            ])->save();

            return $this->recordTransaction(
                $entitlement,
                LeaveTransaction::TYPE_RESTORE,
                $days,
                $balanceBefore,
                $balanceAfter,
                $referenceType,
                $referenceId,
                $remarks,
            );
        });
    }

    public function adjustBalance(LeaveEntitlement $entitlement, float $days, string $remarks): LeaveTransaction
    {
        return DB::transaction(function () use ($entitlement, $days, $remarks): LeaveTransaction {
            $entitlement = $this->lockEntitlement($entitlement);

            if ($days === 0.0) {
                throw ValidationException::withMessages([
                    'days' => 'The adjustment amount must be greater than zero or less than zero.',
                ]);
            }

            $leaveType = $this->resolveLeaveType($entitlement);
            $balanceBefore = (float) $entitlement->remaining_days;
            $balanceAfter = $balanceBefore + $days;

            if ($leaveType->is_paid && $balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'days' => 'The adjustment would make the paid leave balance negative.',
                ]);
            }

            $entitlement->forceFill([
                'remaining_days' => $balanceAfter,
                'remarks' => $remarks,
            ])->save();

            return $this->recordTransaction(
                $entitlement,
                LeaveTransaction::TYPE_ADJUSTMENT,
                $days,
                $balanceBefore,
                $balanceAfter,
                null,
                null,
                $remarks,
            );
        });
    }

    public function recordTransaction(
        LeaveEntitlement $entitlement,
        string $transactionType,
        float $days,
        ?float $balanceBefore = null,
        ?float $balanceAfter = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null,
        ?int $createdBy = null,
    ): LeaveTransaction {
        $leaveType = $this->resolveLeaveType($entitlement);

        return LeaveTransaction::query()->create([
            'company_id' => $entitlement->company_id,
            'employee_id' => $entitlement->employee_id,
            'leave_type_id' => $leaveType->getKey(),
            'leave_entitlement_id' => $entitlement->getKey(),
            'transaction_type' => $transactionType,
            'days' => $days,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'remarks' => $remarks,
            'created_by' => $createdBy ?? $this->resolveAuthenticatedEmployeeId(),
        ]);
    }

    private function lockEntitlement(LeaveEntitlement $entitlement): LeaveEntitlement
    {
        $locked = LeaveEntitlement::query()
            ->with(['employee', 'leaveType'])
            ->whereKey($entitlement->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $this->assertSharedCompanyScope((int) $locked->company_id, (int) $locked->employee->company_id);
        $this->assertSharedCompanyScope((int) $locked->company_id, (int) $locked->leaveType->company_id);

        return $locked;
    }

    private function resolveLeaveType(LeaveEntitlement $entitlement): LeaveType
    {
        $leaveType = $entitlement->relationLoaded('leaveType')
            ? $entitlement->leaveType
            : $entitlement->leaveType()->first();

        if (! $leaveType instanceof LeaveType) {
            throw new RuntimeException('The leave entitlement does not have a valid leave type.');
        }

        return $leaveType;
    }

    private function assertPositiveDays(float $days): void
    {
        if ($days <= 0) {
            throw ValidationException::withMessages([
                'days' => 'The number of leave days must be greater than zero.',
            ]);
        }
    }

    private function assertSharedCompanyScope(int $leftCompanyId, int $rightCompanyId): void
    {
        if ($leftCompanyId !== $rightCompanyId) {
            throw new RuntimeException('Leave balance operations cannot cross company boundaries.');
        }
    }

    private function resolveAuthenticatedEmployeeId(): ?int
    {
        $user = Auth::user();

        return $user instanceof Employee ? (int) $user->getKey() : null;
    }
}
