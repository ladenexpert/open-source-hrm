<?php

namespace App\Enums;

enum ApprovalModuleType: string
{
    case LEAVE = 'leave';
    case ATTENDANCE_CORRECTION = 'attendance_correction';
    case OVERTIME = 'overtime';
    case PAYROLL = 'payroll';
    case SALARY_CHANGE = 'salary_change';
    case MUTATION = 'mutation';
    case PROMOTION = 'promotion';
    case DEMOTION = 'demotion';
    case RECRUITMENT = 'recruitment';
    case APPRAISAL = 'appraisal';
    case EMPLOYEE_DATA_CHANGE = 'employee_data_change';
    case REIMBURSEMENT = 'reimbursement';
    case LOAN = 'loan';

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
