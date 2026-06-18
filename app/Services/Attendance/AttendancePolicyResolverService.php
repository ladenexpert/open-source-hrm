<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use App\Models\Employee;

class AttendancePolicyResolverService
{
    public function resolvePolicy(Employee $employee): ?AttendancePolicy
    {
        $policy = $employee->relationLoaded('attendancePolicy')
            ? $employee->attendancePolicy
            : $employee->attendancePolicy()->first();

        if ($policy instanceof AttendancePolicy) {
            return $policy;
        }

        $company = $employee->relationLoaded('company')
            ? $employee->company
            : $employee->company()->first();

        $defaultPolicy = $company?->relationLoaded('defaultAttendancePolicy')
            ? $company->defaultAttendancePolicy
            : $company?->defaultAttendancePolicy()->first();

        return $defaultPolicy instanceof AttendancePolicy ? $defaultPolicy : null;
    }

    public function resolveLocationMode(Employee $employee): string
    {
        if (filled($employee->attendance_location_mode_override)) {
            return (string) $employee->attendance_location_mode_override;
        }

        return $this->resolvePolicy($employee)?->location_mode ?? AttendancePolicy::LOCATION_MODE_FIXED;
    }
}
