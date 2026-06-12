<?php

namespace App\Policies;

use App\Models\CostCenter;
use App\Models\Employee;

class CostCenterPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, CostCenter $costCenter): bool
    {
        return $this->canManageCompanyHrRecord($user, $costCenter);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, CostCenter $costCenter): bool
    {
        return $this->canManageCompanyHrRecord($user, $costCenter);
    }

    public function delete(Employee $user, CostCenter $costCenter): bool
    {
        return $this->canManageCompanyHrRecord($user, $costCenter);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
