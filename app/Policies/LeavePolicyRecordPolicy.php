<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeavePolicy;

class LeavePolicyRecordPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, LeavePolicy $leavePolicy): bool
    {
        return $this->canManageCompanyHrRecord($user, $leavePolicy);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, LeavePolicy $leavePolicy): bool
    {
        return $this->canManageCompanyHrRecord($user, $leavePolicy);
    }

    public function delete(Employee $user, LeavePolicy $leavePolicy): bool
    {
        return $this->canManageCompanyHrRecord($user, $leavePolicy);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
