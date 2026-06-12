<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Employee;

class AttendancePolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user) || $user->isDepartmentManager();
    }

    public function view(Employee $user, Attendance $attendance): bool
    {
        return $this->canManageHrMasterData($user)
            || $this->canAccessEmployeeOwnedRecord($user, $attendance);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user) || $this->isActiveUser($user);
    }

    public function update(Employee $user, Attendance $attendance): bool
    {
        return $this->canManageHrMasterData($user)
            || $this->canAccessEmployeeOwnedRecord($user, $attendance);
    }

    public function delete(Employee $user, Attendance $attendance): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
