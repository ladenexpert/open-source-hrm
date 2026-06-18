<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AttendanceLog extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const EVENT_CLOCK_IN = 'clock_in';

    public const EVENT_CLOCK_OUT = 'clock_out';

    public const SOURCE_WEB = 'web';

    public const SOURCE_MOBILE = 'mobile';

    public const SOURCE_FINGERPRINT = 'fingerprint';

    public const SOURCE_API = 'api';

    public const SOURCE_ADMIN = 'admin';

    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_date',
        'event_type',
        'clocked_at',
        'source',
        'latitude',
        'longitude',
        'work_location_id',
        'shift_pattern_id',
        'shift_assignment_id',
        'employee_schedule_id',
        'is_valid',
        'validation_message',
        'selfie_path',
        'device_identifier',
        'ip_address',
        'user_agent',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'attendance_date' => 'date',
        'clocked_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'work_location_id' => 'integer',
        'shift_pattern_id' => 'integer',
        'shift_assignment_id' => 'integer',
        'employee_schedule_id' => 'integer',
        'is_valid' => 'boolean',
        'created_by' => 'integer',
    ];

    protected static function newFactory(): AttendanceLogFactory
    {
        return AttendanceLogFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $attendanceLog): void {
            $attendanceLog->attendance_date ??= $attendanceLog->clocked_at?->toDateString();

            if (! in_array($attendanceLog->event_type, self::eventTypes(), true)) {
                throw ValidationException::withMessages([
                    'event_type' => 'The selected attendance event type is invalid.',
                ]);
            }

            if (! in_array($attendanceLog->source, self::sourceOptions(), true)) {
                throw ValidationException::withMessages([
                    'source' => 'The selected attendance source is invalid.',
                ]);
            }

            $attendanceLog->validateCompanyScope();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function eventTypes(): array
    {
        return [
            self::EVENT_CLOCK_IN,
            self::EVENT_CLOCK_OUT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function eventTypeLabels(): array
    {
        return [
            self::EVENT_CLOCK_IN => 'Clock In',
            self::EVENT_CLOCK_OUT => 'Clock Out',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_WEB,
            self::SOURCE_MOBILE,
            self::SOURCE_FINGERPRINT,
            self::SOURCE_API,
            self::SOURCE_ADMIN,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_WEB => 'Web',
            self::SOURCE_MOBILE => 'Mobile',
            self::SOURCE_FINGERPRINT => 'Fingerprint',
            self::SOURCE_API => 'API',
            self::SOURCE_ADMIN => 'Admin',
        ];
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        return null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function employeeSchedule(): BelongsTo
    {
        return $this->belongsTo(EmployeeSchedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function scopeForEmployee(Builder $query, Employee|int|null $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->getKey() : $employee;

        if (blank($employeeId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('employee_id'), $employeeId);
    }

    public function scopeForDate(Builder $query, CarbonInterface|string $date): Builder
    {
        $resolvedDate = $date instanceof CarbonInterface
            ? $date->toDateString()
            : (string) $date;

        return $query->whereDate($this->qualifyColumn('attendance_date'), $resolvedDate);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_valid'), true);
    }

    public function scopeInvalid(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_valid'), false);
    }

    public function isClockIn(): bool
    {
        return $this->event_type === self::EVENT_CLOCK_IN;
    }

    public function isClockOut(): bool
    {
        return $this->event_type === self::EVENT_CLOCK_OUT;
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        $this->assertScopedCompany(Employee::class, $this->employee_id, 'employee_id', $companyId);
        $this->assertScopedCompany(WorkLocation::class, $this->work_location_id, 'work_location_id', $companyId);
        $this->assertScopedCompany(ShiftPattern::class, $this->shift_pattern_id, 'shift_pattern_id', $companyId);
        $this->assertScopedCompany(ShiftAssignment::class, $this->shift_assignment_id, 'shift_assignment_id', $companyId);
        $this->assertScopedCompany(EmployeeSchedule::class, $this->employee_schedule_id, 'employee_schedule_id', $companyId);
        $this->assertScopedCompany(Employee::class, $this->created_by, 'created_by', $companyId);
    }

    private function assertScopedCompany(string $modelClass, ?int $recordId, string $field, ?int $companyId): void
    {
        if (blank($recordId)) {
            return;
        }

        $scopedCompanyId = $modelClass::query()->whereKey($recordId)->value('company_id');

        if (! filled($scopedCompanyId) || (int) $scopedCompanyId !== (int) $companyId) {
            throw ValidationException::withMessages([
                $field => 'The selected record must belong to the selected company.',
            ]);
        }
    }
}
