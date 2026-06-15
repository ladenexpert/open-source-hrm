<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Holiday;

class HolidayPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, Holiday $holiday): bool
    {
        return $this->canManageCompanyHrRecord($user, $holiday);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, Holiday $holiday): bool
    {
        return $this->canManageCompanyHrRecord($user, $holiday);
    }

    public function delete(Employee $user, Holiday $holiday): bool
    {
        return $this->canManageCompanyHrRecord($user, $holiday);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
