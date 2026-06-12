<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Leave;

class LeavePolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user) || $user->isDepartmentManager();
    }

    public function view(Employee $user, Leave $leave): bool
    {
        return $this->canManageCompanyHrRecord($user, $leave)
            || $this->canAccessEmployeeOwnedRecord($user, $leave);
    }

    public function create(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function update(Employee $user, Leave $leave): bool
    {
        return $this->canManageCompanyHrRecord($user, $leave)
            || $this->canAccessEmployeeOwnedRecord($user, $leave);
    }

    public function delete(Employee $user, Leave $leave): bool
    {
        return $this->canManageCompanyHrRecord($user, $leave);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
