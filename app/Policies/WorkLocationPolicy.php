<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\WorkLocation;

class WorkLocationPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, WorkLocation $workLocation): bool
    {
        return $this->canManageCompanyHrRecord($user, $workLocation);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, WorkLocation $workLocation): bool
    {
        return $this->canManageCompanyHrRecord($user, $workLocation);
    }

    public function delete(Employee $user, WorkLocation $workLocation): bool
    {
        return $this->canManageCompanyHrRecord($user, $workLocation);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
