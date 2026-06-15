<?php

namespace App\Models;

use App\Enums\ApprovalApproverType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflowStep extends Model
{
    protected $fillable = [
        'approval_workflow_id',
        'step_order',
        'name',
        'approver_type',
        'approver_role',
        'approver_employee_id',
        'approver_job_level_id',
        'is_required',
        'can_reject',
        'can_return',
        'is_final_step',
    ];

    protected $casts = [
        'approval_workflow_id' => 'integer',
        'step_order' => 'integer',
        'approver_type' => ApprovalApproverType::class,
        'approver_employee_id' => 'integer',
        'approver_job_level_id' => 'integer',
        'is_required' => 'boolean',
        'can_reject' => 'boolean',
        'can_return' => 'boolean',
        'is_final_step' => 'boolean',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    public function approverJobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class, 'approver_job_level_id');
    }

    public function requestSteps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class, 'approval_workflow_step_id');
    }
}
