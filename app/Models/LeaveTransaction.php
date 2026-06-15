<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class LeaveTransaction extends Model
{
    use BelongsToCompany;

    public const TYPE_ENTITLEMENT = 'ENTITLEMENT';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const TYPE_LEAVE_TAKEN = 'LEAVE_TAKEN';

    public const TYPE_CARRY_FORWARD = 'CARRY_FORWARD';

    public const TYPE_FORFEITED = 'FORFEITED';

    public const TYPE_RESTORE = 'RESTORE';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'leave_entitlement_id',
        'transaction_type',
        'days',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'leave_type_id' => 'integer',
        'leave_entitlement_id' => 'integer',
        'days' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'reference_id' => 'integer',
        'created_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $leaveTransaction): void {
            $leaveTransaction->company_id ??= $leaveTransaction->resolveCompanyIdForCreation();

            $leaveTransaction->validateType();
            $leaveTransaction->validateScope();
        });
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_ENTITLEMENT => 'Entitlement',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_LEAVE_TAKEN => 'Leave Taken',
            self::TYPE_CARRY_FORWARD => 'Carry Forward',
            self::TYPE_FORFEITED => 'Forfeited',
            self::TYPE_RESTORE => 'Restore',
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

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveEntitlement(): BelongsTo
    {
        return $this->belongsTo(LeaveEntitlement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('created_at', $year);
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->leave_entitlement_id)) {
            return LeaveEntitlement::query()->whereKey($this->leave_entitlement_id)->value('company_id');
        }

        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        if (filled($this->leave_type_id)) {
            return LeaveType::query()->whereKey($this->leave_type_id)->value('company_id');
        }

        return null;
    }

    private function validateType(): void
    {
        if (! array_key_exists((string) $this->transaction_type, self::typeOptions())) {
            throw ValidationException::withMessages([
                'transaction_type' => 'The selected transaction type is invalid.',
            ]);
        }
    }

    private function validateScope(): void
    {
        $employeeCompanyId = Employee::query()->whereKey($this->employee_id)->value('company_id');
        $leaveTypeCompanyId = LeaveType::query()->whereKey($this->leave_type_id)->value('company_id');

        if (! filled($employeeCompanyId) || (int) $employeeCompanyId !== (int) $this->company_id) {
            throw ValidationException::withMessages([
                'employee_id' => 'The selected employee must belong to the selected company.',
            ]);
        }

        if (! filled($leaveTypeCompanyId) || (int) $leaveTypeCompanyId !== (int) $this->company_id) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'The selected leave type must belong to the selected company.',
            ]);
        }

        if (blank($this->leave_entitlement_id)) {
            return;
        }

        $entitlement = LeaveEntitlement::query()->find($this->leave_entitlement_id);

        if (! $entitlement instanceof LeaveEntitlement || (int) $entitlement->company_id !== (int) $this->company_id) {
            throw ValidationException::withMessages([
                'leave_entitlement_id' => 'The selected leave entitlement must belong to the selected company.',
            ]);
        }
    }
}
