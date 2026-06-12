<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Shift;

class ShiftPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, Shift $shift): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, Shift $shift): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function delete(Employee $user, Shift $shift): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
