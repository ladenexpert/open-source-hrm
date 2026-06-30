<?php

namespace App\Policies;

use App\Models\AttendancePayrollSnapshot;
use App\Models\Employee;

class AttendancePayrollSnapshotPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $user->canManageHrMasterData() || $user->canManagePayroll();
    }

    public function view(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return $this->canManageSnapshot($user, $snapshot);
    }

    public function create(Employee $user): bool
    {
        return $user->canManageHrMasterData() || $user->canManagePayroll();
    }

    public function update(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return false;
    }

    public function delete(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }

    public function calculate(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return $this->canManageSnapshot($user, $snapshot);
    }

    public function recalculate(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return $this->canManageSnapshot($user, $snapshot);
    }

    public function lock(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return $this->canManageSnapshot($user, $snapshot);
    }

    public function markStale(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return $this->canManageSnapshot($user, $snapshot);
    }

    public function cancel(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return $this->canManageSnapshot($user, $snapshot);
    }

    private function canManageSnapshot(Employee $user, AttendancePayrollSnapshot $snapshot): bool
    {
        return ($user->canManageHrMasterData() || $user->canManagePayroll())
            && filled($snapshot->company_id)
            && $user->canAccessCompany((int) $snapshot->company_id);
    }
}
