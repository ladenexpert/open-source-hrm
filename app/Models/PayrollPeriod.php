<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PayrollPeriod extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'period_code',
        'name',
        'period_start',
        'period_end',
        'pay_date',
        'status',
        'locked_at',
        'locked_by',
        'closed_at',
        'closed_by',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'pay_date' => 'date',
        'locked_at' => 'datetime',
        'locked_by' => 'integer',
        'closed_at' => 'datetime',
        'closed_by' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $period): void {
            if (! in_array($period->status, self::statuses(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected payroll period status is invalid.',
                ]);
            }

            if (
                $period->period_start instanceof CarbonInterface
                && $period->period_end instanceof CarbonInterface
                && $period->period_end->lt($period->period_start)
            ) {
                throw ValidationException::withMessages([
                    'period_end' => 'The period end date must be on or after the period start date.',
                ]);
            }

            $period->validateCompanyScope();
            $period->validateNoOverlap();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_OPEN,
            self::STATUS_LOCKED,
            self::STATUS_CLOSED,
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
            self::STATUS_OPEN => 'Open',
            self::STATUS_LOCKED => 'Locked',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_OPEN => 'success',
            self::STATUS_LOCKED => 'primary',
            self::STATUS_CLOSED => 'warning',
            self::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'locked_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'closed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), '!=', self::STATUS_CANCELLED);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        $this->assertScopedCompany(Employee::class, $this->locked_by, 'locked_by', $companyId);
        $this->assertScopedCompany(Employee::class, $this->closed_by, 'closed_by', $companyId);
    }

    private function validateNoOverlap(): void
    {
        if (
            blank($this->company_id)
            || blank($this->period_start)
            || blank($this->period_end)
            || $this->isCancelled()
        ) {
            return;
        }

        $overlapExists = self::query()
            ->where('company_id', $this->company_id)
            ->where('status', '!=', self::STATUS_CANCELLED)
            ->when(
                $this->exists,
                fn (Builder $query): Builder => $query->whereKeyNot($this->getKey()),
            )
            ->whereDate('period_start', '<=', $this->period_end->toDateString())
            ->whereDate('period_end', '>=', $this->period_start->toDateString())
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'period_start' => 'The payroll period overlaps another active payroll period for the same company.',
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
