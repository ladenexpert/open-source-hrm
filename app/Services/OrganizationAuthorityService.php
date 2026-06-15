<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Support\ApprovalRoleMap;

class OrganizationAuthorityService
{
    public function getDirectSupervisor(Employee $employee): ?Employee
    {
        if (blank($employee->direct_supervisor_id)) {
            return null;
        }

        $supervisor = $employee->relationLoaded('directSupervisor')
            ? $employee->directSupervisor
            : $employee->directSupervisor()->first();

        return $supervisor instanceof Employee && $supervisor->is_active
            ? $supervisor
            : null;
    }

    public function getDepartmentHead(Employee $employee): ?Employee
    {
        if (blank($employee->department_id)) {
            return null;
        }

        $department = $employee->relationLoaded('department')
            ? $employee->department
            : Department::query()->with('manager')->find($employee->department_id);

        $manager = $department?->manager;

        return $manager instanceof Employee && $manager->is_active
            ? $manager
            : null;
    }

    public function isDirectSupervisor(Employee $approver, Employee $subject): bool
    {
        $supervisor = $this->getDirectSupervisor($subject);

        return $supervisor instanceof Employee
            && (int) $supervisor->getKey() === (int) $approver->getKey();
    }

    public function isDepartmentHead(Employee $approver, Employee $subject): bool
    {
        $departmentHead = $this->getDepartmentHead($subject);

        return $departmentHead instanceof Employee
            && (int) $departmentHead->getKey() === (int) $approver->getKey();
    }

    public function isSameCompany(Employee $a, Employee $b): bool
    {
        return filled($a->company_id)
            && filled($b->company_id)
            && (int) $a->company_id === (int) $b->company_id;
    }

    public function isSameCompanyGroup(Employee $a, Employee $b): bool
    {
        $groupA = $a->getEffectiveCompanyGroupId();
        $groupB = $b->getEffectiveCompanyGroupId();

        return filled($groupA)
            && filled($groupB)
            && (int) $groupA === (int) $groupB;
    }

    public function canApproveFor(Employee $approver, Employee $subject, string $authorityType): bool
    {
        $authorityType = ApprovalRoleMap::normalize($authorityType);

        return match ($authorityType) {
            'direct_supervisor' => $this->isDirectSupervisor($approver, $subject),
            'department_head' => $this->isDepartmentHead($approver, $subject),
            'hr_head' => $this->isSameCompanyGroup($approver, $subject) && ApprovalRoleMap::matches($approver, 'hr_head'),
            'finance_head' => $this->isSameCompanyGroup($approver, $subject) && ApprovalRoleMap::matches($approver, 'finance_head'),
            'company_head' => $this->isSameCompanyGroup($approver, $subject) && ApprovalRoleMap::matches($approver, 'company_head'),
            default => false,
        };
    }
}
