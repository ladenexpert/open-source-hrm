<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\ShiftPattern;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceLogService
{
    private const CROSS_DATE_DUPLICATE_WINDOW_MINUTES = 120;
    private const INVALID_REPEAT_WINDOW_MINUTES = 5;

    public function __construct(
        private readonly ShiftResolverService $shiftResolverService,
        private readonly AttendanceLocationValidationService $attendanceLocationValidationService,
        private readonly AttendancePolicyResolverService $attendancePolicyResolverService,
        private readonly AttendanceSelfieService $attendanceSelfieService,
        private readonly AttendanceDeviceTrustService $attendanceDeviceTrustService,
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
        $attendancePolicy = $this->attendancePolicyResolverService->resolvePolicy($employee);
        $selfieUpload = $payload['selfie'] ?? null;
        $deviceInfo = is_array($payload['device_info'] ?? null) ? $payload['device_info'] : [];
        $deviceIdentifier = $this->resolveDeviceIdentifier(
            $payload['device_uuid'] ?? $payload['device_identifier'] ?? null,
            $deviceInfo,
        );

        if (($attendancePolicy?->requiresSelfie() ?? false) && ! $this->attendanceSelfieService->hasSelfie($selfieUpload)) {
            throw ValidationException::withMessages([
                'selfie' => sprintf(
                    '%s requires a selfie upload under the active attendance policy.',
                    AttendanceLog::eventTypeLabels()[$eventType] ?? 'This attendance action',
                ),
            ]);
        }

        $this->attendanceDeviceTrustService->registerAttempt(
            $employee,
            $attendancePolicy,
            $deviceIdentifier,
            $deviceInfo,
            $payload['ip_address'] ?? null,
            $payload['user_agent'] ?? null,
            $clockedAt,
        );

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
        $this->ensureNoDuplicateSubmission(
            $employee,
            $eventType,
            $clockedAt,
            (bool) $validation['is_valid'],
        );

        return DB::transaction(function () use (
            $clockedAt,
            $deviceIdentifier,
            $employee,
            $eventType,
            $latitude,
            $longitude,
            $payload,
            $resolution,
            $selfieUpload,
            $validation,
            $workLocation,
        ): AttendanceLog {
            $log = AttendanceLog::query()->create([
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
                'device_identifier' => $deviceIdentifier,
                'ip_address' => $payload['ip_address'] ?? null,
                'user_agent' => $payload['user_agent'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $this->resolveCreatedBy($employee, $payload['created_by'] ?? null),
            ]);

            if ($this->attendanceSelfieService->hasSelfie($selfieUpload)) {
                $this->attendanceSelfieService->storeForAttendance(
                    $employee,
                    $log,
                    $selfieUpload,
                    $payload['captured_at'] ?? $clockedAt,
                    $payload['device_info'] ?? null,
                    $payload['metadata'] ?? null,
                );
            }

            return $log->fresh(['attendanceSelfie']) ?? $log;
        });
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

    private function ensureNoDuplicateSubmission(
        Employee $employee,
        string $eventType,
        Carbon $clockedAt,
        bool $currentAttemptIsValid,
    ): void
    {
        $lastValidLog = AttendanceLog::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->where('event_type', $eventType)
            ->valid()
            ->where('clocked_at', '<=', $clockedAt)
            ->orderByDesc('clocked_at')
            ->orderByDesc('id')
            ->first();

        if ($lastValidLog instanceof AttendanceLog) {
            $isSameAttendanceDate = $lastValidLog->attendance_date?->toDateString() === $clockedAt->toDateString();
            $isRapidRepeat = $lastValidLog->clocked_at?->diffInMinutes($clockedAt) <= self::CROSS_DATE_DUPLICATE_WINDOW_MINUTES;

            if ($isSameAttendanceDate || $isRapidRepeat) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'event_type' => sprintf(
                        '%s is already your latest valid attendance event at %s. Duplicate submissions are blocked.',
                        AttendanceLog::eventTypeLabels()[$eventType] ?? $eventType,
                        $lastValidLog->clocked_at?->setTimezone(config('app.timezone'))->format('d M Y H:i') ?? '-',
                    ),
                ]);
            }
        }

        if ($currentAttemptIsValid) {
            return;
        }

        $lastAttempt = AttendanceLog::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->where('event_type', $eventType)
            ->where('clocked_at', '<=', $clockedAt)
            ->orderByDesc('clocked_at')
            ->orderByDesc('id')
            ->first();

        if (! $lastAttempt instanceof AttendanceLog || $lastAttempt->is_valid) {
            return;
        }

        $isRapidInvalidRepeat = $lastAttempt->clocked_at?->diffInMinutes($clockedAt) <= self::INVALID_REPEAT_WINDOW_MINUTES;

        if (! $isRapidInvalidRepeat) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'event_type' => sprintf(
                '%s attempt at %s was already recorded as invalid. Please wait before retrying and make sure GPS is available.',
                AttendanceLog::eventTypeLabels()[$eventType] ?? $eventType,
                $lastAttempt->clocked_at?->setTimezone(config('app.timezone'))->format('d M Y H:i') ?? '-',
            ),
        ]);
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

    /**
     * @param  array<string, mixed>  $deviceInfo
     */
    private function resolveDeviceIdentifier(mixed $deviceIdentifier, array $deviceInfo): ?string
    {
        $resolvedIdentifier = filled($deviceIdentifier)
            ? (string) $deviceIdentifier
            : (string) ($deviceInfo['device_uuid'] ?? '');

        $resolvedIdentifier = trim($resolvedIdentifier);

        return $resolvedIdentifier !== '' ? $resolvedIdentifier : null;
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
