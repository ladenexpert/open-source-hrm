<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveTransaction;

class LeaveTransactionPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, LeaveTransaction $leaveTransaction): bool
    {
        return $this->canManageCompanyHrRecord($user, $leaveTransaction);
    }

    public function create(Employee $user): bool
    {
        return false;
    }

    public function update(Employee $user, LeaveTransaction $leaveTransaction): bool
    {
        return false;
    }

    public function delete(Employee $user, LeaveTransaction $leaveTransaction): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }
}
