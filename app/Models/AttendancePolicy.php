<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class AttendancePolicy extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use SoftDeletes;

    public const LOCATION_MODE_FIXED = 'fixed';

    public const LOCATION_MODE_FLEXIBLE = 'flexible';

    public const LOCATION_MODE_SCHEDULED = 'scheduled';

    public const TRUSTED_DEVICE_MODE_NONE = 'none';

    public const TRUSTED_DEVICE_MODE_WARN = 'warn';

    public const TRUSTED_DEVICE_MODE_ENFORCE = 'enforce';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'location_mode',
        'gps_required',
        'require_selfie',
        'selfie_required',
        'radius_validation_enabled',
        'radius_meters',
        'trusted_device_mode',
        'auto_trust_first_device',
        'max_trusted_devices',
        'late_tolerance_minutes',
        'early_out_tolerance_minutes',
        'minimum_work_minutes',
        'auto_absent_after_minutes',
        'overtime_threshold_minutes',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'gps_required' => 'boolean',
        'require_selfie' => 'boolean',
        'selfie_required' => 'boolean',
        'radius_validation_enabled' => 'boolean',
        'radius_meters' => 'integer',
        'auto_trust_first_device' => 'boolean',
        'max_trusted_devices' => 'integer',
        'late_tolerance_minutes' => 'integer',
        'early_out_tolerance_minutes' => 'integer',
        'minimum_work_minutes' => 'integer',
        'auto_absent_after_minutes' => 'integer',
        'overtime_threshold_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $attendancePolicy): void {
            $attendancePolicy->trusted_device_mode ??= self::TRUSTED_DEVICE_MODE_NONE;
            $attendancePolicy->auto_trust_first_device ??= false;

            if (! in_array($attendancePolicy->location_mode, self::locationModeOptions(), true)) {
                throw ValidationException::withMessages([
                    'location_mode' => 'The selected attendance location mode is invalid.',
                ]);
            }

            if (! in_array($attendancePolicy->trusted_device_mode, self::trustedDeviceModeOptions(), true)) {
                throw ValidationException::withMessages([
                    'trusted_device_mode' => 'The selected trusted device mode is invalid.',
                ]);
            }

            $requireSelfie = $attendancePolicy->getAttribute('require_selfie');
            $selfieRequired = $attendancePolicy->getAttribute('selfie_required');

            if ($attendancePolicy->isDirty('require_selfie') && ! $attendancePolicy->isDirty('selfie_required')) {
                $selfieRequired = $requireSelfie;
            } elseif ($attendancePolicy->isDirty('selfie_required') && ! $attendancePolicy->isDirty('require_selfie')) {
                $requireSelfie = $selfieRequired;
            }

            $resolvedSelfieRequirement = (bool) ($requireSelfie ?? $selfieRequired ?? false);

            $attendancePolicy->require_selfie = $resolvedSelfieRequirement;
            $attendancePolicy->selfie_required = $resolvedSelfieRequirement;

            if (! $attendancePolicy->radius_validation_enabled) {
                $attendancePolicy->radius_meters = null;
            }

            if (blank($attendancePolicy->max_trusted_devices)) {
                $attendancePolicy->max_trusted_devices = null;
            }

            if (filled($attendancePolicy->max_trusted_devices) && (int) $attendancePolicy->max_trusted_devices < 1) {
                throw ValidationException::withMessages([
                    'max_trusted_devices' => 'The maximum trusted devices value must be at least 1.',
                ]);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public static function locationModeOptions(): array
    {
        return [
            self::LOCATION_MODE_FIXED,
            self::LOCATION_MODE_FLEXIBLE,
            self::LOCATION_MODE_SCHEDULED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function locationModeLabels(): array
    {
        return [
            self::LOCATION_MODE_FIXED => 'Fixed',
            self::LOCATION_MODE_FLEXIBLE => 'Flexible',
            self::LOCATION_MODE_SCHEDULED => 'Scheduled',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function trustedDeviceModeOptions(): array
    {
        return [
            self::TRUSTED_DEVICE_MODE_NONE,
            self::TRUSTED_DEVICE_MODE_WARN,
            self::TRUSTED_DEVICE_MODE_ENFORCE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function trustedDeviceModeLabels(): array
    {
        return [
            self::TRUSTED_DEVICE_MODE_NONE => 'Disabled',
            self::TRUSTED_DEVICE_MODE_WARN => 'Warn',
            self::TRUSTED_DEVICE_MODE_ENFORCE => 'Enforce',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'company_id', 'company_id')
            ->where('assignable_type', ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE)
            ->whereIn('assignable_id', $this->employees()->select('id'));
    }

    public function requiresSelfie(): bool
    {
        return (bool) ($this->require_selfie ?? $this->selfie_required);
    }

    public function enforcesTrustedDevices(): bool
    {
        return $this->trusted_device_mode === self::TRUSTED_DEVICE_MODE_ENFORCE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
