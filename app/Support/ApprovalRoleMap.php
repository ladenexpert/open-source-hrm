<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Str;

class ApprovalRoleMap
{
    public static function workflowManagerRoles(): array
    {
        return ['admin', 'hr', 'hr_admin', 'hr_head'];
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
        return ['company_head', 'admin', 'super_admin'];
    }

    public static function aliasesFor(string $role): array
    {
        $normalizedRole = static::normalize($role);

        return match ($normalizedRole) {
            'hr', 'hr_admin', 'hr_head' => ['hr_head', 'hr_admin', 'hr', 'admin'],
            'finance', 'finance_admin', 'finance_head' => ['finance_head', 'finance_admin', 'finance'],
            'department_manager', 'department_head', 'leader' => ['department_head', 'department_manager', 'leader'],
            'company_head' => ['company_head', 'admin', 'super_admin'],
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
