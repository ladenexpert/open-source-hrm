<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class LeaveEntitlement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'year',
        'entitled_days',
        'carried_forward_days',
        'used_days',
        'remaining_days',
        'expires_at',
        'remarks',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'leave_type_id' => 'integer',
        'year' => 'integer',
        'entitled_days' => 'decimal:2',
        'carried_forward_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'remaining_days' => 'decimal:2',
        'expires_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $leaveEntitlement): void {
            $leaveEntitlement->company_id ??= $leaveEntitlement->resolveCompanyIdForCreation();

            $leaveEntitlement->validateScope();
        });
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

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveTransaction::class)->latest('id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->latest('id');
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        if (filled($this->leave_type_id)) {
            return LeaveType::query()->whereKey($this->leave_type_id)->value('company_id');
        }

        return null;
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
    }
}
