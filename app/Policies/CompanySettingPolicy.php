<?php

namespace App\Policies;

use App\Models\CompanySetting;
use App\Models\Employee;

class CompanySettingPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, CompanySetting $companySetting): bool
    {
        return $this->canManageCompanyHrRecord($user, $companySetting);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, CompanySetting $companySetting): bool
    {
        return $this->canManageCompanyHrRecord($user, $companySetting);
    }

    public function delete(Employee $user, CompanySetting $companySetting): bool
    {
        return $this->canManageCompanyHrRecord($user, $companySetting);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
