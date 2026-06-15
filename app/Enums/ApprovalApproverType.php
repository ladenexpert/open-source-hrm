<?php

namespace App\Enums;

enum ApprovalApproverType: string
{
    case DIRECT_SUPERVISOR = 'direct_supervisor';
    case DEPARTMENT_HEAD = 'department_head';
    case ROLE = 'role';
    case SPECIFIC_EMPLOYEE = 'specific_employee';
    case JOB_LEVEL = 'job_level';
    case HR_HEAD = 'hr_head';
    case FINANCE_HEAD = 'finance_head';
    case COMPANY_HEAD = 'company_head';

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $case): array => [$case->value => str($case->value)->replace('_', ' ')->title()->value()])
            ->all();
    }
}
