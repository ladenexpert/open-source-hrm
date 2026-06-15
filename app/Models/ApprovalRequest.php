<?php

namespace App\Models;

use App\Enums\ApprovalModuleType;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'company_group_id',
        'company_id',
        'approval_workflow_id',
        'approvable_type',
        'approvable_id',
        'requester_id',
        'employee_subject_id',
        'module_type',
        'status',
        'submitted_at',
        'completed_at',
        'current_step_order',
        'summary',
        'payload',
    ];

    protected $casts = [
        'company_group_id' => 'integer',
        'company_id' => 'integer',
        'approval_workflow_id' => 'integer',
        'requester_id' => 'integer',
        'employee_subject_id' => 'integer',
        'module_type' => ApprovalModuleType::class,
        'status' => ApprovalRequestStatus::class,
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'current_step_order' => 'integer',
        'payload' => 'array',
    ];

    public function companyGroup(): BelongsTo
    {
        return $this->belongsTo(CompanyGroup::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function employeeSubject(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_subject_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class)
            ->orderBy('step_order')
            ->orderBy('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalRequestStatus::PENDING);
    }

    public function currentPendingSteps(): HasMany
    {
        return $this->steps()
            ->where('step_order', $this->current_step_order)
            ->where('status', ApprovalStepStatus::PENDING);
    }

    public function isAssignedTo(Employee $employee): bool
    {
        return $this->steps()
            ->where('approver_id', $employee->getKey())
            ->exists();
    }

    public function isPendingForApprover(Employee $employee): bool
    {
        if (blank($this->current_step_order)) {
            return false;
        }

        return $this->steps()
            ->where('step_order', $this->current_step_order)
            ->where('approver_id', $employee->getKey())
            ->where('status', ApprovalStepStatus::PENDING)
            ->exists();
    }
}
