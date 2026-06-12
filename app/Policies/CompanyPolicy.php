<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Employee;

class CompanyPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(Employee $user, Company $company): bool
    {
        return $user->isSuperAdmin()
            || ($this->canManageHrMasterData($user) && (int) $company->id === (int) $this->companyIdFor($user));
    }

    public function create(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(Employee $user, Company $company): bool
    {
        return $this->view($user, $company);
    }

    public function delete(Employee $user, Company $company): bool
    {
        return $user->isSuperAdmin() && $company->code !== Company::DEFAULT_CODE;
    }

    public function deleteAny(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }
}
