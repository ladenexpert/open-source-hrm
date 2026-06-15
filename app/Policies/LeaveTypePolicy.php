<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveType;

class LeaveTypePolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, LeaveType $leaveType): bool
    {
        return $this->canManageCompanyHrRecord($user, $leaveType);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, LeaveType $leaveType): bool
    {
        return $this->canManageCompanyHrRecord($user, $leaveType);
    }

    public function delete(Employee $user, LeaveType $leaveType): bool
    {
        return $this->canManageCompanyHrRecord($user, $leaveType);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
