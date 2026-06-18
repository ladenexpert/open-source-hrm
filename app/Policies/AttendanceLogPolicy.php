<?php

namespace App\Policies;

use App\Models\AttendanceLog;
use App\Models\Employee;

class AttendanceLogPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, AttendanceLog $attendanceLog): bool
    {
        return $this->canManageCompanyHrRecord($user, $attendanceLog)
            || (
                $this->isActiveUser($user)
                && $this->isOwnEmployeeRecord($user, $attendanceLog->employee_id)
                && $this->sharesCompany($user, $attendanceLog)
            );
    }

    public function create(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function update(Employee $user, AttendanceLog $attendanceLog): bool
    {
        return false;
    }

    public function delete(Employee $user, AttendanceLog $attendanceLog): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }
}
