<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeDevice;

class EmployeeDevicePolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, EmployeeDevice $employeeDevice): bool
    {
        return $this->canManageCompanyHrRecord($user, $employeeDevice);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, EmployeeDevice $employeeDevice): bool
    {
        return $this->canManageCompanyHrRecord($user, $employeeDevice);
    }

    public function delete(Employee $user, EmployeeDevice $employeeDevice): bool
    {
        return $this->canManageCompanyHrRecord($user, $employeeDevice);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
