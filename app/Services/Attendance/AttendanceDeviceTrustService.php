<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceDeviceTrustService
{
    public function __construct(
        private readonly AttendancePolicyResolverService $attendancePolicyResolverService,
    ) {
    }

    public function registerAttempt(
        Employee $employee,
        ?AttendancePolicy $attendancePolicy,
        ?string $deviceUuid,
        array $deviceInfo = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
        CarbonInterface|string|null $usedAt = null,
    ): ?EmployeeDevice {
        $normalizedUuid = $this->normalizeDeviceUuid($deviceUuid ?: Arr::get($deviceInfo, 'device_uuid'));

        if (blank($normalizedUuid)) {
            return null;
        }

        $usedAt = $this->resolveUsedAt($usedAt);
        $existingDeviceCount = EmployeeDevice::query()
            ->forEmployee($employee)
            ->count();

        $device = EmployeeDevice::query()->firstOrNew([
            'employee_id' => $employee->getKey(),
            'device_uuid' => $normalizedUuid,
        ]);

        $device->company_id = $employee->getEffectiveCompanyId();
        $device->status ??= EmployeeDevice::STATUS_PENDING;
        $device->device_name = $this->resolveDeviceName(
            Arr::get($deviceInfo, 'device_name'),
            $userAgent ?: Arr::get($deviceInfo, 'user_agent'),
            Arr::get($deviceInfo, 'browser'),
            Arr::get($deviceInfo, 'platform'),
        );
        $device->platform = $this->resolvePlatform(
            Arr::get($deviceInfo, 'platform'),
            $userAgent ?: Arr::get($deviceInfo, 'user_agent'),
        );
        $device->browser = $this->resolveBrowser(
            Arr::get($deviceInfo, 'browser'),
            $userAgent ?: Arr::get($deviceInfo, 'user_agent'),
        );
        $device->user_agent = $userAgent ?: Arr::get($deviceInfo, 'user_agent');
        $device->first_seen_at ??= $usedAt;
        $device->last_used_at = $usedAt;
        $device->last_ip_address = $ipAddress;
        $device->metadata = $this->resolveMetadata($deviceInfo, $device->metadata);

        if (! $device->exists && $this->shouldAutoTrustFirstDevice($attendancePolicy, $existingDeviceCount)) {
            $this->ensureTrustedDeviceCapacity($employee, $attendancePolicy);
            $this->applyTrustedState(
                $device,
                actor: null,
                timestamp: $usedAt,
                reason: 'Automatically trusted as the first registered device.',
            );
        }

        $device->save();

        $this->ensureAttendanceAllowed($device, $attendancePolicy);

        return $device->fresh(['employee', 'trustedBy', 'revokedBy']) ?? $device;
    }

    public function trustDevice(EmployeeDevice $device, Employee $actor, ?string $reason = null): EmployeeDevice
    {
        $device->loadMissing('employee');

        if (! $device->employee instanceof Employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'The selected employee device is missing its employee record.',
            ]);
        }

        $policy = $this->attendancePolicyResolverService->resolvePolicy($device->employee);

        $this->ensureTrustedDeviceCapacity($device->employee, $policy, $device);
        $this->applyTrustedState($device, $actor, now(config('app.timezone')), $reason);
        $device->save();

        return $device->fresh(['employee', 'trustedBy', 'revokedBy']) ?? $device;
    }

    public function revokeDevice(EmployeeDevice $device, Employee $actor, ?string $reason = null): EmployeeDevice
    {
        $device->status = EmployeeDevice::STATUS_REVOKED;
        $device->status_reason = $reason;
        $device->revoked_by = $actor->getKey();
        $device->revoked_at = now(config('app.timezone'));
        $device->save();

        return $device->fresh(['employee', 'trustedBy', 'revokedBy']) ?? $device;
    }

    public function deactivateDevice(EmployeeDevice $device, Employee $actor, ?string $reason = null): EmployeeDevice
    {
        $device->status = EmployeeDevice::STATUS_INACTIVE;
        $device->status_reason = $reason;
        $device->revoked_by = null;
        $device->revoked_at = null;
        $device->save();

        return $device->fresh(['employee', 'trustedBy', 'revokedBy']) ?? $device;
    }

    public function ensureTrustedDeviceCapacity(
        Employee $employee,
        ?AttendancePolicy $attendancePolicy,
        ?EmployeeDevice $except = null,
    ): void {
        $maxTrustedDevices = $attendancePolicy?->max_trusted_devices;

        if (! filled($maxTrustedDevices)) {
            return;
        }

        $trustedCount = EmployeeDevice::query()
            ->forEmployee($employee)
            ->status(EmployeeDevice::STATUS_TRUSTED)
            ->whereNull('revoked_at')
            ->when(
                $except?->exists,
                fn ($query) => $query->whereKeyNot($except->getKey()),
            )
            ->count();

        if ($trustedCount >= (int) $maxTrustedDevices) {
            throw ValidationException::withMessages([
                'status' => 'This employee has reached the maximum number of trusted devices allowed by the active attendance policy.',
            ]);
        }
    }

    private function ensureAttendanceAllowed(EmployeeDevice $device, ?AttendancePolicy $attendancePolicy): void
    {
        if (! $attendancePolicy?->enforcesTrustedDevices()) {
            return;
        }

        if ($device->canBeUsedForAttendance()) {
            return;
        }

        throw ValidationException::withMessages([
            'device_uuid' => 'This device is not trusted for attendance. Please contact HR/Admin to approve this device.',
        ]);
    }

    private function shouldAutoTrustFirstDevice(?AttendancePolicy $attendancePolicy, int $existingDeviceCount): bool
    {
        return (bool) ($attendancePolicy?->auto_trust_first_device ?? false)
            && $existingDeviceCount === 0;
    }

    private function applyTrustedState(
        EmployeeDevice $device,
        ?Employee $actor,
        CarbonInterface $timestamp,
        ?string $reason = null,
    ): void {
        $device->status = EmployeeDevice::STATUS_TRUSTED;
        $device->status_reason = $reason;
        $device->trusted_by = $actor?->getKey();
        $device->trusted_at = Carbon::instance($timestamp);
        $device->revoked_by = null;
        $device->revoked_at = null;
    }

    private function normalizeDeviceUuid(?string $deviceUuid): ?string
    {
        $normalized = trim((string) $deviceUuid);

        return $normalized !== '' ? Str::lower($normalized) : null;
    }

    private function resolveUsedAt(CarbonInterface|string|null $usedAt): Carbon
    {
        $timezone = config('app.timezone');

        if ($usedAt instanceof CarbonInterface) {
            return Carbon::instance($usedAt)->setTimezone($timezone);
        }

        if (filled($usedAt)) {
            return Carbon::parse((string) $usedAt, $timezone);
        }

        return now($timezone);
    }

    /**
     * @param  array<string, mixed>  $deviceInfo
     * @param  array<string, mixed>|null  $existingMetadata
     * @return array<string, mixed>
     */
    private function resolveMetadata(array $deviceInfo, ?array $existingMetadata = null): array
    {
        $metadata = array_merge($existingMetadata ?? [], Arr::except($deviceInfo, [
            'browser',
            'device_name',
            'device_uuid',
            'platform',
            'user_agent',
        ]));

        return collect($metadata)
            ->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function resolveDeviceName(
        mixed $explicitName,
        ?string $userAgent,
        mixed $browser,
        mixed $platform,
    ): ?string {
        $resolvedName = trim((string) $explicitName);

        if ($resolvedName !== '') {
            return $resolvedName;
        }

        $resolvedBrowser = $this->resolveBrowser($browser, $userAgent);
        $resolvedPlatform = $this->resolvePlatform($platform, $userAgent);

        return collect([$resolvedBrowser, $resolvedPlatform])
            ->filter()
            ->join(' on ') ?: null;
    }

    private function resolveBrowser(mixed $browser, ?string $userAgent): ?string
    {
        $explicit = trim((string) $browser);

        if ($explicit !== '') {
            return $explicit;
        }

        $agent = Str::lower((string) $userAgent);

        return match (true) {
            str_contains($agent, 'edg/') => 'Edge',
            str_contains($agent, 'opr/'), str_contains($agent, 'opera') => 'Opera',
            str_contains($agent, 'chrome/') => 'Chrome',
            str_contains($agent, 'firefox/') => 'Firefox',
            str_contains($agent, 'safari/') && ! str_contains($agent, 'chrome/') => 'Safari',
            str_contains($agent, 'trident/'), str_contains($agent, 'msie') => 'Internet Explorer',
            default => null,
        };
    }

    private function resolvePlatform(mixed $platform, ?string $userAgent): ?string
    {
        $explicit = trim((string) $platform);

        if ($explicit !== '') {
            return $explicit;
        }

        $agent = Str::lower((string) $userAgent);

        return match (true) {
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'iphone'), str_contains($agent, 'ipad'), str_contains($agent, 'ios') => 'iOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'mac os'), str_contains($agent, 'macintosh') => 'macOS',
            str_contains($agent, 'linux') => 'Linux',
            default => null,
        };
    }
}
