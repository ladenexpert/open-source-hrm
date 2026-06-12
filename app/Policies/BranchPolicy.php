<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Employee;

class BranchPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, Branch $branch): bool
    {
        return $this->canManageCompanyHrRecord($user, $branch);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, Branch $branch): bool
    {
        return $this->canManageCompanyHrRecord($user, $branch);
    }

    public function delete(Employee $user, Branch $branch): bool
    {
        return $this->canManageCompanyHrRecord($user, $branch);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
