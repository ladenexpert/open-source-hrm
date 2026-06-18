<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\ShiftPattern;

class ShiftPatternPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, ShiftPattern $shiftPattern): bool
    {
        return $this->canManageCompanyHrRecord($user, $shiftPattern);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, ShiftPattern $shiftPattern): bool
    {
        return $this->canManageCompanyHrRecord($user, $shiftPattern);
    }

    public function delete(Employee $user, ShiftPattern $shiftPattern): bool
    {
        return $this->canManageCompanyHrRecord($user, $shiftPattern);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
