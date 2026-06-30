<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\OvertimeCalculationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OvertimeCalculation extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_STALE = 'stale';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'overtime_request_id',
        'attendance_summary_id',
        'calculation_date',
        'scheduled_end_at',
        'actual_clock_out_at',
        'actual_overtime_minutes',
        'requested_minutes',
        'approved_minutes',
        'calculated_minutes',
        'calculation_status',
        'calculated_at',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'overtime_request_id' => 'integer',
        'attendance_summary_id' => 'integer',
        'calculation_date' => 'date',
        'scheduled_end_at' => 'datetime',
        'actual_clock_out_at' => 'datetime',
        'actual_overtime_minutes' => 'integer',
        'requested_minutes' => 'integer',
        'approved_minutes' => 'integer',
        'calculated_minutes' => 'integer',
        'calculated_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function newFactory(): OvertimeCalculationFactory
    {
        return OvertimeCalculationFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $overtimeCalculation): void {
            if (! in_array($overtimeCalculation->calculation_status, self::statuses(), true)) {
                throw ValidationException::withMessages([
                    'calculation_status' => 'The selected overtime calculation status is invalid.',
                ]);
            }

            foreach ([
                'actual_overtime_minutes',
                'requested_minutes',
                'approved_minutes',
                'calculated_minutes',
            ] as $field) {
                if (filled($overtimeCalculation->{$field}) && (int) $overtimeCalculation->{$field} < 0) {
                    throw ValidationException::withMessages([
                        $field => 'Minutes must be zero or greater.',
                    ]);
                }
            }

            $overtimeCalculation->validateCompanyScope();
            $overtimeCalculation->validateLinks();
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
            self::STATUS_STALE => 'Stale',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_CALCULATED => 'success',
            self::STATUS_STALE => 'warning',
            self::STATUS_CANCELLED => 'secondary',
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

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
    }

    public function attendanceSummary(): BelongsTo
    {
        return $this->belongsTo(AttendanceSummary::class);
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

        return $query->whereDate($this->qualifyColumn('calculation_date'), $resolvedDate);
    }

    public function scopeStatus(Builder $query, string|array $status): Builder
    {
        $statuses = is_array($status) ? $status : [$status];

        return $query->whereIn($this->qualifyColumn('calculation_status'), $statuses);
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        $this->assertScopedCompany(Employee::class, $this->employee_id, 'employee_id', $companyId);
        $this->assertScopedCompany(OvertimeRequest::class, $this->overtime_request_id, 'overtime_request_id', $companyId);
        $this->assertScopedCompany(AttendanceSummary::class, $this->attendance_summary_id, 'attendance_summary_id', $companyId);
    }

    private function validateLinks(): void
    {
        /** @var OvertimeRequest|null $request */
        $request = filled($this->overtime_request_id)
            ? OvertimeRequest::query()->find($this->overtime_request_id)
            : null;

        if ($request instanceof OvertimeRequest) {
            if ((int) $request->employee_id !== (int) $this->employee_id) {
                throw ValidationException::withMessages([
                    'overtime_request_id' => 'The linked overtime request must belong to the same employee.',
                ]);
            }

            if ($request->overtime_date->toDateString() !== $this->calculation_date->toDateString()) {
                throw ValidationException::withMessages([
                    'overtime_request_id' => 'The linked overtime request must match the calculation date.',
                ]);
            }
        }

        /** @var AttendanceSummary|null $summary */
        $summary = filled($this->attendance_summary_id)
            ? AttendanceSummary::query()->find($this->attendance_summary_id)
            : null;

        if ($summary instanceof AttendanceSummary) {
            if ((int) $summary->employee_id !== (int) $this->employee_id) {
                throw ValidationException::withMessages([
                    'attendance_summary_id' => 'The linked attendance summary must belong to the same employee.',
                ]);
            }

            if ($summary->attendance_date->toDateString() !== $this->calculation_date->toDateString()) {
                throw ValidationException::withMessages([
                    'attendance_summary_id' => 'The linked attendance summary must match the calculation date.',
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
