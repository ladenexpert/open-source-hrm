<?php

namespace App\Policies;

use App\Models\Employee;

class EmployeePolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user) || $user->isDepartmentManager();
    }

    public function view(Employee $user, Employee $employee): bool
    {
        return $this->canManageCompanyHrRecord($user, $employee)
            || $this->isOwnEmployeeRecord($user, $employee->id)
            || $this->canManageEmployeeDepartment($user, $employee);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, Employee $employee): bool
    {
        return $this->canManageCompanyHrRecord($user, $employee)
            || $this->isOwnEmployeeRecord($user, $employee->id)
            || $this->canManageEmployeeDepartment($user, $employee);
    }

    public function delete(Employee $user, Employee $employee): bool
    {
        return $this->canManageCompanyHrRecord($user, $employee);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
