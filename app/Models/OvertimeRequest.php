<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\OvertimeRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class OvertimeRequest extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_summary_id',
        'overtime_date',
        'requested_start_at',
        'requested_end_at',
        'requested_minutes',
        'reason',
        'status',
        'submitted_at',
        'submitted_by',
        'approved_minutes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'approval_request_id',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'attendance_summary_id' => 'integer',
        'overtime_date' => 'date',
        'requested_start_at' => 'datetime',
        'requested_end_at' => 'datetime',
        'requested_minutes' => 'integer',
        'submitted_at' => 'datetime',
        'submitted_by' => 'integer',
        'approved_minutes' => 'integer',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'rejected_by' => 'integer',
        'rejected_at' => 'datetime',
        'cancelled_by' => 'integer',
        'cancelled_at' => 'datetime',
        'approval_request_id' => 'integer',
        'metadata' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected static function newFactory(): OvertimeRequestFactory
    {
        return OvertimeRequestFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $overtimeRequest): void {
            if (! in_array($overtimeRequest->status, self::statuses(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected overtime request status is invalid.',
                ]);
            }

            if (filled($overtimeRequest->requested_minutes) && (int) $overtimeRequest->requested_minutes < 0) {
                throw ValidationException::withMessages([
                    'requested_minutes' => 'Requested minutes must be zero or greater.',
                ]);
            }

            if (filled($overtimeRequest->approved_minutes) && (int) $overtimeRequest->approved_minutes < 0) {
                throw ValidationException::withMessages([
                    'approved_minutes' => 'Approved minutes must be zero or greater.',
                ]);
            }

            if (
                $overtimeRequest->requested_start_at !== null
                && $overtimeRequest->requested_end_at !== null
                && ! $overtimeRequest->requested_end_at->greaterThan($overtimeRequest->requested_start_at)
            ) {
                throw ValidationException::withMessages([
                    'requested_end_at' => 'Requested end time must be after the requested start time.',
                ]);
            }

            $overtimeRequest->validateCompanyScope();
            $overtimeRequest->validateAttendanceSummaryLink();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
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
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_SUBMITTED => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
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

    public function attendanceSummary(): BelongsTo
    {
        return $this->belongsTo(AttendanceSummary::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function calculation(): HasOne
    {
        return $this->hasOne(OvertimeCalculation::class);
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

        return $query->whereDate($this->qualifyColumn('overtime_date'), $resolvedDate);
    }

    public function scopeStatus(Builder $query, string|array $status): Builder
    {
        $statuses = is_array($status) ? $status : [$status];

        return $query->whereIn($this->qualifyColumn('status'), $statuses);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_SUBMITTED);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_APPROVED);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
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
            self::STATUS_SUBMITTED,
        ], true);
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        $this->assertScopedCompany(Employee::class, $this->employee_id, 'employee_id', $companyId);
        $this->assertScopedCompany(AttendanceSummary::class, $this->attendance_summary_id, 'attendance_summary_id', $companyId);
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

        if ($summary->attendance_date->toDateString() !== $this->overtime_date->toDateString()) {
            throw ValidationException::withMessages([
                'attendance_summary_id' => 'The selected attendance summary must match the selected overtime date.',
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
