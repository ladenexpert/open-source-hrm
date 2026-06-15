<?php

namespace App\Policies;

use App\Models\CompanyGroup;
use App\Models\Employee;

class CompanyGroupPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, CompanyGroup $companyGroup): bool
    {
        return $user->isSuperAdmin()
            || $user->canAccessCompanyGroup($companyGroup->id);
    }

    public function create(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(Employee $user, CompanyGroup $companyGroup): bool
    {
        return $user->isSuperAdmin()
            || ($this->canManageHrMasterData($user) && $user->canAccessCompanyGroup($companyGroup->id));
    }

    public function delete(Employee $user, CompanyGroup $companyGroup): bool
    {
        return $user->isSuperAdmin() && $companyGroup->code !== CompanyGroup::DEFAULT_CODE;
    }

    public function deleteAny(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }
}
