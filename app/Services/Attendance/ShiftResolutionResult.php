<?php

namespace App\Services\Attendance;

use App\Models\EmployeeSchedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;

class ShiftResolutionResult
{
    public function __construct(
        public readonly ?ShiftPattern $shiftPattern,
        public readonly ?ShiftAssignment $shiftAssignment,
        public readonly ?EmployeeSchedule $employeeSchedule,
        public readonly string $source,
    ) {
    }

    public function isDayOffOverride(): bool
    {
        return $this->source === 'employee_schedule_day_off';
    }

    public function isUnassigned(): bool
    {
        return $this->source === 'unassigned';
    }
}
