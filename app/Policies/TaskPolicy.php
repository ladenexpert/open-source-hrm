<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Task;

class TaskPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, Task $task): bool
    {
        return $this->canManageCompanyHrRecord($user, $task)
            || $this->isOwnEmployeeRecord($user, $task->assignee_id)
            || $this->canManageEmployeeDepartment($user, $task->assignee);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user) || $user->isDepartmentManager();
    }

    public function update(Employee $user, Task $task): bool
    {
        return $this->canManageCompanyHrRecord($user, $task)
            || $this->isOwnEmployeeRecord($user, $task->assignee_id)
            || $this->canManageEmployeeDepartment($user, $task->assignee);
    }

    public function delete(Employee $user, Task $task): bool
    {
        return $this->canManageCompanyHrRecord($user, $task)
            || $this->canManageEmployeeDepartment($user, $task->assignee);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user) || $user->isDepartmentManager();
    }
}
