<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\ShiftPattern;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class AttendanceLogService
{
    public function __construct(
        private readonly ShiftResolverService $shiftResolverService,
        private readonly AttendanceLocationValidationService $attendanceLocationValidationService,
    ) {
    }

    public function clockIn(Employee $employee, array $payload = []): AttendanceLog
    {
        return $this->record($employee, AttendanceLog::EVENT_CLOCK_IN, $payload);
    }

    public function clockOut(Employee $employee, array $payload = []): AttendanceLog
    {
        return $this->record($employee, AttendanceLog::EVENT_CLOCK_OUT, $payload);
    }

    public function record(Employee $employee, string $eventType, array $payload = []): AttendanceLog
    {
        if (! in_array($eventType, AttendanceLog::eventTypes(), true)) {
            throw new InvalidArgumentException('Unsupported attendance event type.');
        }

        $clockedAt = $this->resolveClockedAt($payload['clocked_at'] ?? null);
        $resolution = $this->shiftResolverService->resolve($employee, $clockedAt->copy());
        $workLocation = $this->resolveWorkLocation($employee, $resolution);
        $latitude = $this->resolveFloat($payload['latitude'] ?? null);
        $longitude = $this->resolveFloat($payload['longitude'] ?? null);
        $validation = $this->attendanceLocationValidationService->validate(
            $employee,
            $latitude,
            $longitude,
            $workLocation,
        );

        return AttendanceLog::query()->create([
            'company_id' => $employee->getEffectiveCompanyId(),
            'employee_id' => $employee->getKey(),
            'attendance_date' => $clockedAt->toDateString(),
            'event_type' => $eventType,
            'clocked_at' => $clockedAt,
            'source' => $this->resolveSource($payload['source'] ?? null),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'work_location_id' => $workLocation?->getKey(),
            'shift_pattern_id' => $resolution->shiftPattern?->getKey(),
            'shift_assignment_id' => $resolution->shiftAssignment?->getKey(),
            'employee_schedule_id' => $resolution->employeeSchedule?->getKey(),
            'is_valid' => $validation['is_valid'],
            'validation_message' => $validation['message'],
            'selfie_path' => $payload['selfie_path'] ?? null,
            'device_identifier' => $payload['device_identifier'] ?? null,
            'ip_address' => $payload['ip_address'] ?? null,
            'user_agent' => $payload['user_agent'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $this->resolveCreatedBy($employee, $payload['created_by'] ?? null),
        ]);
    }

    private function resolveClockedAt(mixed $value): Carbon
    {
        $timezone = config('app.timezone');

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->setTimezone($timezone);
        }

        if (filled($value)) {
            return Carbon::parse((string) $value, $timezone);
        }

        return now($timezone);
    }

    private function resolveSource(mixed $source): string
    {
        $resolvedSource = filled($source)
            ? (string) $source
            : AttendanceLog::SOURCE_WEB;

        if (! in_array($resolvedSource, AttendanceLog::sourceOptions(), true)) {
            throw new InvalidArgumentException('Unsupported attendance source.');
        }

        return $resolvedSource;
    }

    private function resolveCreatedBy(Employee $employee, mixed $createdBy): int
    {
        if ($createdBy instanceof Employee) {
            return (int) $createdBy->getKey();
        }

        if (filled($createdBy)) {
            return (int) $createdBy;
        }

        return (int) $employee->getKey();
    }

    private function resolveFloat(mixed $value): ?float
    {
        return filled($value) ? (float) $value : null;
    }

    private function resolveWorkLocation(Employee $employee, ShiftResolutionResult $resolution): ?WorkLocation
    {
        if ($resolution->employeeSchedule?->workLocation instanceof WorkLocation) {
            return $resolution->employeeSchedule->workLocation;
        }

        if ($resolution->shiftAssignment?->workLocation instanceof WorkLocation) {
            return $resolution->shiftAssignment->workLocation;
        }

        if ($employee->relationLoaded('workLocation') && $employee->workLocation instanceof WorkLocation) {
            return $employee->workLocation;
        }

        $workLocation = $employee->workLocation()->first();

        return $workLocation instanceof WorkLocation ? $workLocation : null;
    }
}
