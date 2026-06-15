<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class LeavePolicy extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'leave_type_id',
        'employment_status_id',
        'job_level_id',
        'entitlement_days',
        'minimum_service_months',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'leave_type_id' => 'integer',
        'employment_status_id' => 'integer',
        'job_level_id' => 'integer',
        'entitlement_days' => 'decimal:2',
        'minimum_service_months' => 'integer',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $leavePolicy): void {
            $leavePolicy->company_id ??= LeaveType::query()
                ->whereKey($leavePolicy->leave_type_id)
                ->value('company_id');

            $leavePolicy->validateReferences();
            $leavePolicy->validateEffectiveDates();
        });
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->leave_type_id)) {
            return LeaveType::query()->whereKey($this->leave_type_id)->value('company_id');
        }

        return null;
    }

    private function validateReferences(): void
    {
        $companyId = $this->company_id;
        $companyGroupId = Company::query()->whereKey($companyId)->value('company_group_id');

        $leaveTypeCompanyId = LeaveType::query()->whereKey($this->leave_type_id)->value('company_id');

        if (! filled($leaveTypeCompanyId) || (int) $leaveTypeCompanyId !== (int) $companyId) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'The selected leave type must belong to the selected company.',
            ]);
        }

        $this->validateScopedMasterDataReference(EmploymentStatus::class, $this->employment_status_id, 'employment_status_id', $companyId, $companyGroupId);
        $this->validateScopedMasterDataReference(JobLevel::class, $this->job_level_id, 'job_level_id', $companyId, $companyGroupId);
    }

    private function validateEffectiveDates(): void
    {
        if (filled($this->effective_from) && filled($this->effective_until) && $this->effective_until->lt($this->effective_from)) {
            throw ValidationException::withMessages([
                'effective_until' => 'Effective until date must be after the effective from date.',
            ]);
        }
    }

    private function validateScopedMasterDataReference(
        string $modelClass,
        ?int $recordId,
        string $field,
        ?int $companyId,
        ?int $companyGroupId,
    ): void {
        if (blank($recordId)) {
            return;
        }

        /** @var \App\Models\MasterData|null $record */
        $record = $modelClass::query()->find($recordId);

        if (! $record || ! $record->isAvailableFor($companyId, $companyGroupId)) {
            throw ValidationException::withMessages([
                $field => 'The selected value is outside the allowed company or company group scope.',
            ]);
        }
    }
}
