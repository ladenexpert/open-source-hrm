<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveEntitlement;

class LeaveEntitlementPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, LeaveEntitlement $leaveEntitlement): bool
    {
        return $this->canManageCompanyHrRecord($user, $leaveEntitlement);
    }

    public function create(Employee $user): bool
    {
        return false;
    }

    public function update(Employee $user, LeaveEntitlement $leaveEntitlement): bool
    {
        return $this->canManageCompanyHrRecord($user, $leaveEntitlement);
    }

    public function delete(Employee $user, LeaveEntitlement $leaveEntitlement): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }
}
