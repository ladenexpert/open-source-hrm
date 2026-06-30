<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\OvertimeCalculation;

class OvertimeCalculationPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, OvertimeCalculation $overtimeCalculation): bool
    {
        return $this->canManageCompanyHrRecord($user, $overtimeCalculation)
            || (
                $this->isActiveUser($user)
                && $this->isOwnEmployeeRecord($user, $overtimeCalculation->employee_id)
                && $this->sharesCompany($user, $overtimeCalculation)
            );
    }

    public function create(Employee $user): bool
    {
        return false;
    }

    public function update(Employee $user, OvertimeCalculation $overtimeCalculation): bool
    {
        return false;
    }

    public function delete(Employee $user, OvertimeCalculation $overtimeCalculation): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }
}
