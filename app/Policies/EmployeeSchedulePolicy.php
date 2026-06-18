<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeSchedule;

class EmployeeSchedulePolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, EmployeeSchedule $employeeSchedule): bool
    {
        return $this->canManageCompanyHrRecord($user, $employeeSchedule);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, EmployeeSchedule $employeeSchedule): bool
    {
        return $this->canManageCompanyHrRecord($user, $employeeSchedule);
    }

    public function delete(Employee $user, EmployeeSchedule $employeeSchedule): bool
    {
        return $this->canManageCompanyHrRecord($user, $employeeSchedule);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
