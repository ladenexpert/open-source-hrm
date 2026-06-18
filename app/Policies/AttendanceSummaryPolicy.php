<?php

namespace App\Policies;

use App\Models\AttendanceSummary;
use App\Models\Employee;

class AttendanceSummaryPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, AttendanceSummary $attendanceSummary): bool
    {
        return $this->canManageCompanyHrRecord($user, $attendanceSummary)
            || (
                $this->isActiveUser($user)
                && $this->isOwnEmployeeRecord($user, $attendanceSummary->employee_id)
                && $this->sharesCompany($user, $attendanceSummary)
            );
    }

    public function create(Employee $user): bool
    {
        return false;
    }

    public function update(Employee $user, AttendanceSummary $attendanceSummary): bool
    {
        return false;
    }

    public function delete(Employee $user, AttendanceSummary $attendanceSummary): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }

    public function recalculate(Employee $user, AttendanceSummary $attendanceSummary): bool
    {
        return $this->canManageCompanyHrRecord($user, $attendanceSummary);
    }
}
