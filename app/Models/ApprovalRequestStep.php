<?php

namespace App\Models;

use App\Enums\ApprovalApproverType;
use App\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequestStep extends Model
{
    protected $fillable = [
        'approval_request_id',
        'approval_workflow_step_id',
        'step_order',
        'approver_id',
        'approver_type',
        'status',
        'acted_at',
        'comments',
    ];

    protected $casts = [
        'approval_request_id' => 'integer',
        'approval_workflow_step_id' => 'integer',
        'step_order' => 'integer',
        'approver_id' => 'integer',
        'approver_type' => ApprovalApproverType::class,
        'status' => ApprovalStepStatus::class,
        'acted_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'approval_workflow_step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}
