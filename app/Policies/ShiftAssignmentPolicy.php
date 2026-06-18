<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\ShiftAssignment;

class ShiftAssignmentPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, ShiftAssignment $shiftAssignment): bool
    {
        return $this->canManageCompanyHrRecord($user, $shiftAssignment);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, ShiftAssignment $shiftAssignment): bool
    {
        return $this->canManageCompanyHrRecord($user, $shiftAssignment);
    }

    public function delete(Employee $user, ShiftAssignment $shiftAssignment): bool
    {
        return $this->canManageCompanyHrRecord($user, $shiftAssignment);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
