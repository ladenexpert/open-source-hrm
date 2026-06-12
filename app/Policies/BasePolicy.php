<?php

namespace App\Policies;

use App\Models\Company;
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

    protected function companyIdFor(Employee $user): ?int
    {
        return $user->getEffectiveCompanyId();
    }

    protected function sharesCompany(Employee $user, Model $record, string $companyColumn = 'company_id'): bool
    {
        $recordCompanyId = $record->getAttribute($companyColumn);

        if (blank($recordCompanyId)) {
            $recordCompanyId = Company::getDefaultCompanyId();
        }

        return filled($recordCompanyId)
            && (int) $recordCompanyId === (int) $this->companyIdFor($user);
    }

    protected function canManageCompanyHrRecord(Employee $user, Model $record): bool
    {
        return $this->canManageHrMasterData($user) && $this->sharesCompany($user, $record);
    }

    protected function canManageCompanyPayrollRecord(Employee $user, Model $record): bool
    {
        return $this->canManagePayroll($user) && $this->sharesCompany($user, $record);
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
        if (! $this->sharesCompany($user, $record)) {
            return false;
        }

        $employeeId = $record->getAttribute($employeeIdColumn);

        if ($this->isOwnEmployeeRecord($user, $employeeId)) {
            return true;
        }

        $relatedEmployee = $record->relationLoaded($employeeRelation)
            ? $record->getRelation($employeeRelation)
            : $record->{$employeeRelation};

        return $relatedEmployee instanceof Employee
            && $relatedEmployee->belongsToCompany($this->companyIdFor($user))
            && $this->canManageEmployeeDepartment($user, $relatedEmployee);
    }
}
