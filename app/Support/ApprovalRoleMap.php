<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Str;

class ApprovalRoleMap
{
    public static function workflowManagerRoles(): array
    {
        return ['company_group_admin', 'company_admin', 'admin', 'hr', 'hr_admin', 'hr_head'];
    }

    public static function financeRoles(): array
    {
        return ['finance', 'finance_admin', 'finance_head'];
    }

    public static function departmentLeaderRoles(): array
    {
        return ['department_manager', 'department_head', 'leader'];
    }

    public static function companyHeadRoles(): array
    {
        return ['company_head', 'company_group_admin', 'super_admin'];
    }

    public static function aliasesFor(string $role): array
    {
        $normalizedRole = static::normalize($role);

        return match ($normalizedRole) {
            'company_group_admin' => ['company_group_admin'],
            'company_admin', 'admin' => ['company_admin', 'admin'],
            'hr', 'hr_admin' => ['hr_admin', 'hr'],
            'hr_head' => ['hr_head'],
            'finance', 'finance_admin' => ['finance_admin', 'finance'],
            'finance_head' => ['finance_head'],
            'department_manager', 'department_head', 'leader' => ['department_head', 'department_manager', 'leader'],
            'company_head' => ['company_head', 'company_group_admin', 'super_admin'],
            default => [$normalizedRole],
        };
    }

    public static function matches(Employee $employee, string|array $roles): bool
    {
        $candidates = collect((array) $roles)
            ->flatMap(static fn (string $role): array => static::aliasesFor($role))
            ->unique()
            ->values()
            ->all();

        return $employee->hasAnyNormalizedRole($candidates);
    }

    public static function normalize(string $role): string
    {
        return Str::of($role)
            ->lower()
            ->replace(['-', ' '], '_')
            ->value();
    }
}
