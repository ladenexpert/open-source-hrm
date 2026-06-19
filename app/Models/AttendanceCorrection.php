<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceCorrectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AttendanceCorrection extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_MISSING_CLOCK_IN = 'missing_clock_in';

    public const TYPE_MISSING_CLOCK_OUT = 'missing_clock_out';

    public const TYPE_WRONG_CLOCK_IN = 'wrong_clock_in';

    public const TYPE_WRONG_CLOCK_OUT = 'wrong_clock_out';

    public const TYPE_LOCATION_ISSUE = 'location_issue';

    public const TYPE_INVALID_LOG_REVIEW = 'invalid_log_review';

    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_summary_id',
        'attendance_date',
        'correction_type',
        'reason',
        'requested_clock_in_at',
        'requested_clock_out_at',
        'requested_work_location_id',
        'requested_notes',
        'approved_clock_in_at',
        'approved_clock_out_at',
        'approved_work_location_id',
        'approved_notes',
        'status',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'cancelled_at',
        'cancelled_by',
        'approval_request_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'attendance_summary_id' => 'integer',
        'attendance_date' => 'date',
        'requested_clock_in_at' => 'datetime',
        'requested_clock_out_at' => 'datetime',
        'requested_work_location_id' => 'integer',
        'approved_clock_in_at' => 'datetime',
        'approved_clock_out_at' => 'datetime',
        'approved_work_location_id' => 'integer',
        'submitted_at' => 'datetime',
        'submitted_by' => 'integer',
        'approved_at' => 'datetime',
        'approved_by' => 'integer',
        'rejected_at' => 'datetime',
        'rejected_by' => 'integer',
        'cancelled_at' => 'datetime',
        'cancelled_by' => 'integer',
        'approval_request_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected static function newFactory(): AttendanceCorrectionFactory
    {
        return AttendanceCorrectionFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $attendanceCorrection): void {
            if (! in_array($attendanceCorrection->status, self::statuses(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected attendance correction status is invalid.',
                ]);
            }

            if (! in_array($attendanceCorrection->correction_type, self::correctionTypes(), true)) {
                throw ValidationException::withMessages([
                    'correction_type' => 'The selected attendance correction type is invalid.',
                ]);
            }

            $attendanceCorrection->validateCompanyScope();
            $attendanceCorrection->validateAttendanceSummaryLink();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
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
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'gray',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function correctionTypes(): array
    {
        return [
            self::TYPE_MISSING_CLOCK_IN,
            self::TYPE_MISSING_CLOCK_OUT,
            self::TYPE_WRONG_CLOCK_IN,
            self::TYPE_WRONG_CLOCK_OUT,
            self::TYPE_LOCATION_ISSUE,
            self::TYPE_INVALID_LOG_REVIEW,
            self::TYPE_MANUAL_ADJUSTMENT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function correctionTypeLabels(): array
    {
        return [
            self::TYPE_MISSING_CLOCK_IN => 'Missing Clock In',
            self::TYPE_MISSING_CLOCK_OUT => 'Missing Clock Out',
            self::TYPE_WRONG_CLOCK_IN => 'Wrong Clock In',
            self::TYPE_WRONG_CLOCK_OUT => 'Wrong Clock Out',
            self::TYPE_LOCATION_ISSUE => 'Location Issue',
            self::TYPE_INVALID_LOG_REVIEW => 'Invalid Log Review',
            self::TYPE_MANUAL_ADJUSTMENT => 'Manual Adjustment',
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

    public function attendanceSummary(): BelongsTo
    {
        return $this->belongsTo(AttendanceSummary::class);
    }

    public function requestedWorkLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'requested_work_location_id');
    }

    public function approvedWorkLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'approved_work_location_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cancelled_by');
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

    public function scopePending(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_REJECTED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_CANCELLED);
    }

    public function scopeStatus(Builder $query, string|array $status): Builder
    {
        $statuses = is_array($status) ? $status : [$status];

        return $query->whereIn($this->qualifyColumn('status'), $statuses);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canSubmit(): bool
    {
        return $this->isDraft();
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
        ], true);
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        $this->assertScopedCompany(Employee::class, $this->employee_id, 'employee_id', $companyId);
        $this->assertScopedCompany(AttendanceSummary::class, $this->attendance_summary_id, 'attendance_summary_id', $companyId);
        $this->assertScopedCompany(WorkLocation::class, $this->requested_work_location_id, 'requested_work_location_id', $companyId);
        $this->assertScopedCompany(WorkLocation::class, $this->approved_work_location_id, 'approved_work_location_id', $companyId);
        $this->assertScopedCompany(Employee::class, $this->submitted_by, 'submitted_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->approved_by, 'approved_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->rejected_by, 'rejected_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->cancelled_by, 'cancelled_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->created_by, 'created_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->updated_by, 'updated_by', $companyId);
        $this->assertScopedCompany(ApprovalRequest::class, $this->approval_request_id, 'approval_request_id', $companyId);
    }

    private function validateAttendanceSummaryLink(): void
    {
        if (blank($this->attendance_summary_id)) {
            return;
        }

        /** @var AttendanceSummary|null $summary */
        $summary = AttendanceSummary::query()->find($this->attendance_summary_id);

        if (! $summary instanceof AttendanceSummary) {
            throw ValidationException::withMessages([
                'attendance_summary_id' => 'The selected attendance summary is invalid.',
            ]);
        }

        if ((int) $summary->employee_id !== (int) $this->employee_id) {
            throw ValidationException::withMessages([
                'attendance_summary_id' => 'The selected attendance summary must belong to the same employee.',
            ]);
        }

        if ($summary->attendance_date->toDateString() !== $this->attendance_date->toDateString()) {
            throw ValidationException::withMessages([
                'attendance_summary_id' => 'The selected attendance summary must match the selected attendance date.',
            ]);
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
