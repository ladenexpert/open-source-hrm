<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\WorkdayPattern;

class WorkdayPatternPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, WorkdayPattern $workdayPattern): bool
    {
        return $this->canManageCompanyHrRecord($user, $workdayPattern);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, WorkdayPattern $workdayPattern): bool
    {
        return $this->canManageCompanyHrRecord($user, $workdayPattern);
    }

    public function delete(Employee $user, WorkdayPattern $workdayPattern): bool
    {
        return $this->canManageCompanyHrRecord($user, $workdayPattern);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
