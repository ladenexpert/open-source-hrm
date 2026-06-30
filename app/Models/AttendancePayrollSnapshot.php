<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AttendancePayrollSnapshot extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_STALE = 'stale';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'period_start',
        'period_end',
        'total_work_days',
        'total_present_days',
        'total_absent_days',
        'total_late_minutes',
        'total_early_leave_minutes',
        'total_work_minutes',
        'total_overtime_minutes',
        'total_leave_days',
        'total_correction_count',
        'snapshot_status',
        'calculated_at',
        'locked_at',
        'locked_by',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'total_work_days' => 'integer',
        'total_present_days' => 'integer',
        'total_absent_days' => 'integer',
        'total_late_minutes' => 'integer',
        'total_early_leave_minutes' => 'integer',
        'total_work_minutes' => 'integer',
        'total_overtime_minutes' => 'integer',
        'total_leave_days' => 'decimal:2',
        'total_correction_count' => 'integer',
        'calculated_at' => 'datetime',
        'locked_at' => 'datetime',
        'locked_by' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $snapshot): void {
            if (! in_array($snapshot->snapshot_status, self::statuses(), true)) {
                throw ValidationException::withMessages([
                    'snapshot_status' => 'The selected attendance payroll snapshot status is invalid.',
                ]);
            }

            if (
                $snapshot->period_start instanceof CarbonInterface
                && $snapshot->period_end instanceof CarbonInterface
                && $snapshot->period_end->lt($snapshot->period_start)
            ) {
                throw ValidationException::withMessages([
                    'period_end' => 'The period end date must be on or after the period start date.',
                ]);
            }

            $snapshot->validateCompanyScope();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_CALCULATED,
            self::STATUS_LOCKED,
            self::STATUS_STALE,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_CALCULATED => 'Calculated',
            self::STATUS_LOCKED => 'Locked',
            self::STATUS_STALE => 'Stale',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_CALCULATED => 'success',
            self::STATUS_LOCKED => 'primary',
            self::STATUS_STALE => 'warning',
            self::STATUS_CANCELLED => 'danger',
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

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'locked_by');
    }

    public function scopeForEmployee(Builder $query, Employee|int|null $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->getKey() : $employee;

        if (blank($employeeId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('employee_id'), $employeeId);
    }

    public function scopeBetweenPeriods(
        Builder $query,
        CarbonInterface|string $periodStart,
        CarbonInterface|string $periodEnd,
    ): Builder {
        $resolvedStart = $periodStart instanceof CarbonInterface
            ? $periodStart->toDateString()
            : (string) $periodStart;
        $resolvedEnd = $periodEnd instanceof CarbonInterface
            ? $periodEnd->toDateString()
            : (string) $periodEnd;

        return $query
            ->whereDate($this->qualifyColumn('period_start'), '>=', $resolvedStart)
            ->whereDate($this->qualifyColumn('period_end'), '<=', $resolvedEnd);
    }

    public function isLocked(): bool
    {
        return $this->snapshot_status === self::STATUS_LOCKED;
    }

    public function isCalculated(): bool
    {
        return $this->snapshot_status === self::STATUS_CALCULATED;
    }

    public function isStale(): bool
    {
        return $this->snapshot_status === self::STATUS_STALE;
    }

    public function isCancelled(): bool
    {
        return $this->snapshot_status === self::STATUS_CANCELLED;
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        $this->assertScopedCompany(Employee::class, $this->employee_id, 'employee_id', $companyId);
        $this->assertScopedCompany(Employee::class, $this->locked_by, 'locked_by', $companyId);
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
