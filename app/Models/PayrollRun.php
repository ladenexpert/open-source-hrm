<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PayrollRun extends Model
{
    use BelongsToCompany;

    public const RUN_TYPE_REGULAR = 'regular';

    public const RUN_TYPE_CORRECTION = 'correction';

    public const RUN_TYPE_OFF_CYCLE = 'off_cycle';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PREPARED = 'prepared';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'run_code',
        'run_type',
        'status',
        'period_start',
        'period_end',
        'total_employees',
        'ready_employees',
        'blocked_employees',
        'prepared_at',
        'locked_at',
        'locked_by',
        'approved_at',
        'approved_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'payroll_period_id' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'total_employees' => 'integer',
        'ready_employees' => 'integer',
        'blocked_employees' => 'integer',
        'prepared_at' => 'datetime',
        'locked_at' => 'datetime',
        'locked_by' => 'integer',
        'approved_at' => 'datetime',
        'approved_by' => 'integer',
        'cancelled_at' => 'datetime',
        'cancelled_by' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $run): void {
            if (filled($run->payroll_period_id)) {
                $period = PayrollPeriod::query()->find($run->payroll_period_id);

                if ($period instanceof PayrollPeriod) {
                    $run->company_id ??= $period->company_id;
                    $run->period_start ??= $period->period_start?->toDateString();
                    $run->period_end ??= $period->period_end?->toDateString();
                }
            }

            if (! in_array($run->run_type, self::runTypes(), true)) {
                throw ValidationException::withMessages([
                    'run_type' => 'The selected payroll run type is invalid.',
                ]);
            }

            if (! in_array($run->status, self::statuses(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected payroll run status is invalid.',
                ]);
            }

            if (
                $run->period_start instanceof CarbonInterface
                && $run->period_end instanceof CarbonInterface
                && $run->period_end->lt($run->period_start)
            ) {
                throw ValidationException::withMessages([
                    'period_end' => 'The period end date must be on or after the period start date.',
                ]);
            }

            $run->validateCompanyScope();
            $run->validateDuplicateRegularRun();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function runTypes(): array
    {
        return [
            self::RUN_TYPE_REGULAR,
            self::RUN_TYPE_CORRECTION,
            self::RUN_TYPE_OFF_CYCLE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function runTypeLabels(): array
    {
        return [
            self::RUN_TYPE_REGULAR => 'Regular',
            self::RUN_TYPE_CORRECTION => 'Correction',
            self::RUN_TYPE_OFF_CYCLE => 'Off Cycle',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PREPARED,
            self::STATUS_LOCKED,
            self::STATUS_APPROVED,
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
            self::STATUS_PREPARED => 'Prepared',
            self::STATUS_LOCKED => 'Locked',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PREPARED => 'info',
            self::STATUS_LOCKED => 'primary',
            self::STATUS_APPROVED => 'success',
            self::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->payroll_period_id)) {
            return PayrollPeriod::query()->whereKey($this->payroll_period_id)->value('company_id');
        }

        return null;
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function payrollRunEmployees(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'locked_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPrepared(): bool
    {
        return $this->status === self::STATUS_PREPARED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), '!=', self::STATUS_CANCELLED);
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        if (filled($this->payroll_period_id)) {
            $periodCompanyId = PayrollPeriod::query()->whereKey($this->payroll_period_id)->value('company_id');

            if (! filled($periodCompanyId) || (int) $periodCompanyId !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'payroll_period_id' => 'The selected payroll period must belong to the selected company.',
                ]);
            }
        }

        $this->assertScopedCompany(Employee::class, $this->locked_by, 'locked_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->approved_by, 'approved_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->cancelled_by, 'cancelled_by', $companyId);
    }

    private function validateDuplicateRegularRun(): void
    {
        if (
            $this->run_type !== self::RUN_TYPE_REGULAR
            || $this->isCancelled()
            || blank($this->payroll_period_id)
            || blank($this->company_id)
        ) {
            return;
        }

        $duplicateExists = self::query()
            ->where('company_id', $this->company_id)
            ->where('payroll_period_id', $this->payroll_period_id)
            ->where('run_type', self::RUN_TYPE_REGULAR)
            ->where('status', '!=', self::STATUS_CANCELLED)
            ->when(
                $this->exists,
                fn (Builder $query): Builder => $query->whereKeyNot($this->getKey()),
            )
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'run_type' => 'An active regular payroll run already exists for the selected payroll period.',
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
