<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PayrollRunEmployee extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_EXCLUDED = 'excluded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'employee_id',
        'attendance_payroll_snapshot_id',
        'status',
        'readiness_message',
        'snapshot_status',
        'total_work_days',
        'total_present_days',
        'total_absent_days',
        'total_late_minutes',
        'total_early_leave_minutes',
        'total_work_minutes',
        'total_overtime_minutes',
        'total_leave_days',
        'total_correction_count',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'payroll_run_id' => 'integer',
        'employee_id' => 'integer',
        'attendance_payroll_snapshot_id' => 'integer',
        'total_work_days' => 'integer',
        'total_present_days' => 'integer',
        'total_absent_days' => 'integer',
        'total_late_minutes' => 'integer',
        'total_early_leave_minutes' => 'integer',
        'total_work_minutes' => 'integer',
        'total_overtime_minutes' => 'integer',
        'total_leave_days' => 'decimal:2',
        'total_correction_count' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $runEmployee): void {
            if (! in_array($runEmployee->status, self::statuses(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected payroll run employee status is invalid.',
                ]);
            }

            $runEmployee->validateCompanyScope();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_READY,
            self::STATUS_BLOCKED,
            self::STATUS_EXCLUDED,
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
            self::STATUS_READY => 'Ready',
            self::STATUS_BLOCKED => 'Blocked',
            self::STATUS_EXCLUDED => 'Excluded',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_READY => 'success',
            self::STATUS_BLOCKED => 'danger',
            self::STATUS_EXCLUDED => 'warning',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->payroll_run_id)) {
            return PayrollRun::query()->whereKey($this->payroll_run_id)->value('company_id');
        }

        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        return null;
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendancePayrollSnapshot(): BelongsTo
    {
        return $this->belongsTo(AttendancePayrollSnapshot::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        if (filled($this->payroll_run_id)) {
            $runCompanyId = PayrollRun::query()->whereKey($this->payroll_run_id)->value('company_id');

            if (! filled($runCompanyId) || (int) $runCompanyId !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'payroll_run_id' => 'The selected payroll run must belong to the selected company.',
                ]);
            }
        }

        if (filled($this->employee_id)) {
            $employeeCompanyId = Employee::query()->whereKey($this->employee_id)->value('company_id');

            if (! filled($employeeCompanyId) || (int) $employeeCompanyId !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'employee_id' => 'The selected employee must belong to the selected company.',
                ]);
            }
        }

        if (filled($this->attendance_payroll_snapshot_id)) {
            $snapshot = AttendancePayrollSnapshot::query()
                ->select(['company_id', 'employee_id'])
                ->find($this->attendance_payroll_snapshot_id);

            if (! $snapshot || (int) $snapshot->company_id !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'attendance_payroll_snapshot_id' => 'The selected attendance payroll snapshot must belong to the selected company.',
                ]);
            }

            if (filled($this->employee_id) && (int) $snapshot->employee_id !== (int) $this->employee_id) {
                throw ValidationException::withMessages([
                    'attendance_payroll_snapshot_id' => 'The selected attendance payroll snapshot must belong to the selected employee.',
                ]);
            }
        }
    }
}
