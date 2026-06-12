<?php

namespace App\Policies;

use App\Models\Employee;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    use HandlesAuthorization;

    public function before(Employee $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    protected function isActiveUser(Employee $user): bool
    {
        return (bool) $user->is_active;
    }

    protected function canManageHrMasterData(Employee $user): bool
    {
        return $user->canManageHrMasterData();
    }

    protected function canManagePayroll(Employee $user): bool
    {
        return $user->canManagePayroll();
    }

    protected function isOwnEmployeeRecord(Employee $user, ?int $employeeId): bool
    {
        return filled($employeeId) && ((int) $user->getKey() === (int) $employeeId);
    }

    protected function canManageDepartment(Employee $user, ?int $departmentId): bool
    {
        return filled($departmentId) && $user->managesDepartment($departmentId);
    }

    protected function canManageEmployeeDepartment(Employee $user, ?Employee $employee): bool
    {
        return $employee instanceof Employee
            && $this->canManageDepartment($user, $employee->department_id);
    }

    protected function canAccessEmployeeOwnedRecord(
        Employee $user,
        Model $record,
        string $employeeIdColumn = 'employee_id',
        string $employeeRelation = 'employee',
    ): bool {
        $employeeId = $record->getAttribute($employeeIdColumn);

        if ($this->isOwnEmployeeRecord($user, $employeeId)) {
            return true;
        }

        $relatedEmployee = $record->relationLoaded($employeeRelation)
            ? $record->getRelation($employeeRelation)
            : $record->{$employeeRelation};

        return $relatedEmployee instanceof Employee
            && $this->canManageEmployeeDepartment($user, $relatedEmployee);
    }
}
