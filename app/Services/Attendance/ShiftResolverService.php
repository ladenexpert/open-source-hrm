<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use App\Models\ShiftPatternDetail;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ShiftResolverService
{
    public function resolveShift(Employee $employee, Carbon $date): ?ShiftPattern
    {
        return $this->resolve($employee, $date)->shiftPattern;
    }

    public function getDetailForDate(Employee $employee, Carbon $date): ?ShiftPatternDetail
    {
        $shiftPattern = $this->resolveShift($employee, $date);

        if (! $shiftPattern instanceof ShiftPattern) {
            return null;
        }

        $detail = $shiftPattern->details()
            ->where('day_of_week', $this->normalizeDayOfWeek($date))
            ->first();

        if (! $detail instanceof ShiftPatternDetail || ! $detail->is_working_day) {
            return null;
        }

        return $detail;
    }

    public function resolveWorkLocation(Employee $employee, Carbon $date): ?WorkLocation
    {
        $resolution = $this->resolve($employee, $date);

        if ($resolution->employeeSchedule?->workLocation instanceof WorkLocation) {
            return $resolution->employeeSchedule->workLocation;
        }

        if ($resolution->shiftAssignment?->workLocation instanceof WorkLocation) {
            return $resolution->shiftAssignment->workLocation;
        }

        $workLocation = $employee->relationLoaded('workLocation')
            ? $employee->workLocation
            : $employee->workLocation()->first();

        return $workLocation instanceof WorkLocation ? $workLocation : null;
    }

    public function resolve(Employee $employee, Carbon $date): ShiftResolutionResult
    {
        $companyId = (int) $employee->getEffectiveCompanyId();
        $resolvedDate = $date->copy()->startOfDay();

        $employeeSchedule = EmployeeSchedule::query()
            ->with(['shiftPattern', 'workLocation'])
            ->forCompany($companyId)
            ->where('employee_id', $employee->getKey())
            ->forDate($resolvedDate)
            ->first();

        if ($employeeSchedule instanceof EmployeeSchedule) {
            if ($employeeSchedule->shiftPattern instanceof ShiftPattern) {
                return new ShiftResolutionResult(
                    $employeeSchedule->shiftPattern,
                    null,
                    $employeeSchedule,
                    'employee_schedule_override',
                );
            }

            return new ShiftResolutionResult(null, null, $employeeSchedule, 'employee_schedule_day_off');
        }

        $assignment = $this->findAssignment($employee, $resolvedDate, ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE, $employee->id);

        if ($assignment instanceof ShiftAssignment) {
            return new ShiftResolutionResult($assignment->shiftPattern, $assignment, null, 'employee_assignment');
        }

        if (filled($employee->department_id)) {
            $assignment = $this->findAssignment($employee, $resolvedDate, ShiftAssignment::ASSIGNABLE_TYPE_DEPARTMENT, (int) $employee->department_id);

            if ($assignment instanceof ShiftAssignment) {
                return new ShiftResolutionResult($assignment->shiftPattern, $assignment, null, 'department_assignment');
            }
        }

        if (filled($employee->branch_id)) {
            $assignment = $this->findAssignment($employee, $resolvedDate, ShiftAssignment::ASSIGNABLE_TYPE_BRANCH, (int) $employee->branch_id);

            if ($assignment instanceof ShiftAssignment) {
                return new ShiftResolutionResult($assignment->shiftPattern, $assignment, null, 'branch_assignment');
            }
        }

        $company = $employee->relationLoaded('company')
            ? $employee->company
            : $employee->company()->first();

        $defaultShiftPattern = $company?->relationLoaded('defaultShiftPattern')
            ? $company->defaultShiftPattern
            : $company?->defaultShiftPattern()->first();

        if ($defaultShiftPattern instanceof ShiftPattern) {
            return new ShiftResolutionResult($defaultShiftPattern, null, null, 'company_default');
        }

        Log::warning('Shift resolver returned no configured shift pattern.', [
            'employee_id' => $employee->getKey(),
            'company_id' => $companyId,
            'date' => $resolvedDate->toDateString(),
        ]);

        return new ShiftResolutionResult(null, null, null, 'unassigned');
    }

    private function findAssignment(Employee $employee, Carbon $date, string $type, int $assignableId): ?ShiftAssignment
    {
        return ShiftAssignment::query()
            ->with(['shiftPattern', 'workLocation'])
            ->forCompany($employee->getEffectiveCompanyId())
            ->forAssignable($type, $assignableId)
            ->activeOn($date)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    private function normalizeDayOfWeek(Carbon $date): int
    {
        return $date->dayOfWeek === 0 ? 7 : $date->dayOfWeek;
    }
}
