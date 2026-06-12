<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Position;

class PositionPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user) || $user->isDepartmentManager();
    }

    public function view(Employee $user, Position $position): bool
    {
        return $this->canManageHrMasterData($user)
            || $this->canManageDepartment($user, $position->department_id);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, Position $position): bool
    {
        return $this->canManageHrMasterData($user)
            || $this->canManageDepartment($user, $position->department_id);
    }

    public function delete(Employee $user, Position $position): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
