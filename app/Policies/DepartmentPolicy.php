<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Employee;

class DepartmentPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user) || $user->isDepartmentManager();
    }

    public function view(Employee $user, Department $department): bool
    {
        return $this->canManageHrMasterData($user)
            || $this->canManageDepartment($user, $department->id);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, Department $department): bool
    {
        return $this->canManageHrMasterData($user)
            || $this->canManageDepartment($user, $department->id);
    }

    public function delete(Employee $user, Department $department): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
