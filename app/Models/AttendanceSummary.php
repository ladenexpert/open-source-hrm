<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceSummaryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class AttendanceSummary extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_PRESENT = 'present';

    public const STATUS_LATE = 'late';

    public const STATUS_EARLY_OUT = 'early_out';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_WEEKEND = 'weekend';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_NO_SCHEDULE = 'no_schedule';

    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_date',
        'shift_pattern_id',
        'shift_pattern_detail_id',
        'shift_assignment_id',
        'employee_schedule_id',
        'attendance_policy_id',
        'work_location_id',
        'scheduled_start_at',
        'scheduled_end_at',
        'break_duration_minutes',
        'actual_in_at',
        'actual_out_at',
        'first_log_id',
        'last_log_id',
        'work_minutes',
        'late_minutes',
        'early_out_minutes',
        'status',
        'is_complete',
        'is_recalculated',
        'calculated_at',
        'calculation_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'attendance_date' => 'date',
        'shift_pattern_id' => 'integer',
        'shift_pattern_detail_id' => 'integer',
        'shift_assignment_id' => 'integer',
        'employee_schedule_id' => 'integer',
        'attendance_policy_id' => 'integer',
        'work_location_id' => 'integer',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'break_duration_minutes' => 'integer',
        'actual_in_at' => 'datetime',
        'actual_out_at' => 'datetime',
        'first_log_id' => 'integer',
        'last_log_id' => 'integer',
        'work_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_out_minutes' => 'integer',
        'is_complete' => 'boolean',
        'is_recalculated' => 'boolean',
        'calculated_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected static function newFactory(): AttendanceSummaryFactory
    {
        return AttendanceSummaryFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $attendanceSummary): void {
            if (! in_array($attendanceSummary->status, self::statuses(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected attendance summary status is invalid.',
                ]);
            }

            if ((int) $attendanceSummary->break_duration_minutes < 0) {
                throw ValidationException::withMessages([
                    'break_duration_minutes' => 'Break duration minutes must be zero or greater.',
                ]);
            }

            $attendanceSummary->validateCompanyScope();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PRESENT,
            self::STATUS_LATE,
            self::STATUS_EARLY_OUT,
            self::STATUS_ABSENT,
            self::STATUS_HOLIDAY,
            self::STATUS_WEEKEND,
            self::STATUS_LEAVE,
            self::STATUS_INCOMPLETE,
            self::STATUS_NO_SCHEDULE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PRESENT => 'Present',
            self::STATUS_LATE => 'Late',
            self::STATUS_EARLY_OUT => 'Early Out',
            self::STATUS_ABSENT => 'Absent',
            self::STATUS_HOLIDAY => 'Holiday',
            self::STATUS_WEEKEND => 'Weekend',
            self::STATUS_LEAVE => 'Leave',
            self::STATUS_INCOMPLETE => 'Incomplete',
            self::STATUS_NO_SCHEDULE => 'No Schedule',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PRESENT => 'success',
            self::STATUS_LATE,
            self::STATUS_EARLY_OUT => 'warning',
            self::STATUS_ABSENT => 'danger',
            self::STATUS_LEAVE => 'info',
            self::STATUS_HOLIDAY,
            self::STATUS_WEEKEND,
            self::STATUS_NO_SCHEDULE => 'primary',
            self::STATUS_INCOMPLETE => 'gray',
            default => 'gray',
        };
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

    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    public function shiftPatternDetail(): BelongsTo
    {
        return $this->belongsTo(ShiftPatternDetail::class);
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function employeeSchedule(): BelongsTo
    {
        return $this->belongsTo(EmployeeSchedule::class);
    }

    public function attendancePolicy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function firstLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class, 'first_log_id');
    }

    public function lastLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class, 'last_log_id');
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function overtimeCalculations(): HasMany
    {
        return $this->hasMany(OvertimeCalculation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by');
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

    public function scopeBetweenDates(
        Builder $query,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
    ): Builder {
        $resolvedStartDate = $startDate instanceof CarbonInterface
            ? $startDate->toDateString()
            : (string) $startDate;
        $resolvedEndDate = $endDate instanceof CarbonInterface
            ? $endDate->toDateString()
            : (string) $endDate;

        return $query->whereBetween($this->qualifyColumn('attendance_date'), [$resolvedStartDate, $resolvedEndDate]);
    }

    public function scopeStatus(Builder $query, string|array $status): Builder
    {
        $statuses = is_array($status) ? $status : [$status];

        return $query->whereIn($this->qualifyColumn('status'), $statuses);
    }

    public function scopeComplete(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_complete'), true);
    }

    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_complete'), false);
    }

    public function isPresent(): bool
    {
        return $this->status === self::STATUS_PRESENT;
    }

    public function isLate(): bool
    {
        return $this->status === self::STATUS_LATE;
    }

    public function isAbsent(): bool
    {
        return $this->status === self::STATUS_ABSENT;
    }

    public function isIncomplete(): bool
    {
        return $this->status === self::STATUS_INCOMPLETE;
    }

    public function isLeave(): bool
    {
        return $this->status === self::STATUS_LEAVE;
    }

    public function isHoliday(): bool
    {
        return $this->status === self::STATUS_HOLIDAY;
    }

    public function isWeekend(): bool
    {
        return $this->status === self::STATUS_WEEKEND;
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        $this->assertScopedCompany(Employee::class, $this->employee_id, 'employee_id', $companyId);
        $this->assertScopedCompany(ShiftPattern::class, $this->shift_pattern_id, 'shift_pattern_id', $companyId);
        $this->assertScopedCompany(ShiftAssignment::class, $this->shift_assignment_id, 'shift_assignment_id', $companyId);
        $this->assertScopedCompany(EmployeeSchedule::class, $this->employee_schedule_id, 'employee_schedule_id', $companyId);
        $this->assertScopedCompany(AttendancePolicy::class, $this->attendance_policy_id, 'attendance_policy_id', $companyId);
        $this->assertScopedCompany(WorkLocation::class, $this->work_location_id, 'work_location_id', $companyId);
        $this->assertScopedCompany(AttendanceLog::class, $this->first_log_id, 'first_log_id', $companyId);
        $this->assertScopedCompany(AttendanceLog::class, $this->last_log_id, 'last_log_id', $companyId);
        $this->assertScopedCompany(Employee::class, $this->created_by, 'created_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->updated_by, 'updated_by', $companyId);

        if (filled($this->shift_pattern_detail_id)) {
            $detailCompanyId = ShiftPatternDetail::query()->whereKey($this->shift_pattern_detail_id)->value('company_id');

            if (! filled($detailCompanyId) || (int) $detailCompanyId !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'shift_pattern_detail_id' => 'The selected shift pattern detail must belong to the selected company.',
                ]);
            }
        }
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
