<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class EmployeeSchedule extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use SoftDeletes;

    public const OVERRIDE_REASON_SWAP = 'swap';

    public const OVERRIDE_REASON_TEMPORARY = 'temporary';

    public const OVERRIDE_REASON_HR_OVERRIDE = 'hr_override';

    public const OVERRIDE_REASON_PERSONAL = 'personal';

    protected $fillable = [
        'company_id',
        'employee_id',
        'schedule_date',
        'shift_pattern_id',
        'work_location_id',
        'override_reason',
        'requested_by',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'schedule_date' => 'date',
        'shift_pattern_id' => 'integer',
        'work_location_id' => 'integer',
        'requested_by' => 'integer',
        'approved_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $employeeSchedule): void {
            if (! in_array($employeeSchedule->override_reason, self::overrideReasonOptions(), true)) {
                throw ValidationException::withMessages([
                    'override_reason' => 'The selected override reason is invalid.',
                ]);
            }

            $employeeSchedule->company_id ??= Employee::query()
                ->whereKey($employeeSchedule->employee_id)
                ->value('company_id');

            $employeeCompanyId = Employee::query()->whereKey($employeeSchedule->employee_id)->value('company_id');

            if (! filled($employeeCompanyId) || (int) $employeeCompanyId !== (int) $employeeSchedule->company_id) {
                throw ValidationException::withMessages([
                    'employee_id' => 'The selected employee must belong to the selected company.',
                ]);
            }

            if (filled($employeeSchedule->shift_pattern_id)) {
                $shiftPatternCompanyId = ShiftPattern::query()->whereKey($employeeSchedule->shift_pattern_id)->value('company_id');

                if (! filled($shiftPatternCompanyId) || (int) $shiftPatternCompanyId !== (int) $employeeSchedule->company_id) {
                    throw ValidationException::withMessages([
                        'shift_pattern_id' => 'The selected shift pattern must belong to the selected company.',
                    ]);
                }
            }

            if (filled($employeeSchedule->work_location_id)) {
                $workLocationCompanyId = WorkLocation::query()->whereKey($employeeSchedule->work_location_id)->value('company_id');

                if (! filled($workLocationCompanyId) || (int) $workLocationCompanyId !== (int) $employeeSchedule->company_id) {
                    throw ValidationException::withMessages([
                        'work_location_id' => 'The selected work location must belong to the selected company.',
                    ]);
                }
            }

            foreach (['requested_by', 'approved_by'] as $column) {
                $employeeId = $employeeSchedule->getAttribute($column);

                if (blank($employeeId)) {
                    continue;
                }

                $scopedEmployeeCompanyId = Employee::query()->whereKey($employeeId)->value('company_id');

                if (! filled($scopedEmployeeCompanyId) || (int) $scopedEmployeeCompanyId !== (int) $employeeSchedule->company_id) {
                    throw ValidationException::withMessages([
                        $column => 'The selected employee must belong to the selected company.',
                    ]);
                }
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public static function overrideReasonOptions(): array
    {
        return [
            self::OVERRIDE_REASON_SWAP,
            self::OVERRIDE_REASON_TEMPORARY,
            self::OVERRIDE_REASON_HR_OVERRIDE,
            self::OVERRIDE_REASON_PERSONAL,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function overrideReasonLabels(): array
    {
        return [
            self::OVERRIDE_REASON_SWAP => 'Swap',
            self::OVERRIDE_REASON_TEMPORARY => 'Temporary',
            self::OVERRIDE_REASON_HR_OVERRIDE => 'HR Override',
            self::OVERRIDE_REASON_PERSONAL => 'Personal',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('schedule_date', $date->toDateString());
    }
}
