<?php

namespace App\Services\Leave;

use App\Enums\ApprovalModuleType;
use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\ApprovalActionService;
use App\Services\ApprovalRequestService;
use App\Services\LeaveRequestService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveApprovalService
{
    public function __construct(
        private readonly ApprovalRequestService $approvalRequestService,
        private readonly ApprovalActionService $approvalActionService,
    ) {
    }

    public function initiateApproval(LeaveRequest $leaveRequest): ApprovalRequest
    {
        if ($leaveRequest->approvalRequest()->exists()) {
            throw new RuntimeException('An approval request already exists for this leave request.');
        }

        $employee = $leaveRequest->employee()->first();

        if (! $employee instanceof Employee) {
            throw new RuntimeException('The leave request employee could not be resolved for approval submission.');
        }

        return $this->approvalRequestService->submit(
            $leaveRequest,
            $employee,
            ApprovalModuleType::LEAVE->value,
            $employee,
            $this->buildSummary($leaveRequest),
            $this->buildPayload($leaveRequest),
        );
    }

    public function processApproval(
        ApprovalRequest $approvalRequest,
        Employee $approver,
        string $decision,
        ?string $notes = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($approvalRequest, $approver, $decision, $notes): ApprovalRequest {
            $leaveRequest = $this->resolveLeaveRequest($approvalRequest);

            $updatedApprovalRequest = match ($decision) {
                'approved' => $this->approvalActionService->approveCurrentStep($approvalRequest, $approver, $notes),
                'rejected' => $this->approvalActionService->rejectCurrentStep($approvalRequest, $approver, $notes),
                default => throw new RuntimeException("Unsupported leave approval decision [{$decision}]."),
            };

            if ($decision === 'approved' && $updatedApprovalRequest->status === ApprovalRequestStatus::APPROVED) {
                app(LeaveRequestService::class)->approve($leaveRequest, $approver);
            }

            if ($decision === 'rejected') {
                app(LeaveRequestService::class)->reject($leaveRequest, $approver, $notes);
            }

            return $updatedApprovalRequest->fresh([
                'workflow.steps',
                'steps.workflowStep',
                'steps.approver',
                'logs.actor',
                'requester',
                'employeeSubject',
                'approvable',
            ]);
        });
    }

    public function cancelPendingApproval(LeaveRequest $leaveRequest, Employee $actor, ?string $comments = null): ?ApprovalRequest
    {
        $approvalRequest = $leaveRequest->approvalRequest()->first();

        if (! $approvalRequest instanceof ApprovalRequest) {
            return null;
        }

        if (! in_array($approvalRequest->status, [
            ApprovalRequestStatus::DRAFT,
            ApprovalRequestStatus::PENDING,
        ], true)) {
            return $approvalRequest;
        }

        return $this->approvalActionService->cancelRequest($approvalRequest, $actor, $comments);
    }

    private function resolveLeaveRequest(ApprovalRequest $approvalRequest): LeaveRequest
    {
        $approvalRequest->loadMissing('approvable');
        $approvable = $approvalRequest->approvable;

        if (! $approvable instanceof LeaveRequest) {
            throw new RuntimeException('The approval request is not linked to a leave request.');
        }

        return $approvable;
    }

    private function buildSummary(LeaveRequest $leaveRequest): string
    {
        $leaveRequest->loadMissing(['employee', 'leaveType']);

        $employeeName = $leaveRequest->employee?->full_name ?? 'Unknown employee';
        $leaveTypeName = $leaveRequest->leaveType?->name ?? 'Leave';

        return sprintf(
            '%s requested %s from %s to %s (%s day(s)).',
            $employeeName,
            $leaveTypeName,
            $leaveRequest->start_date->toDateString(),
            $leaveRequest->end_date->toDateString(),
            number_format((float) $leaveRequest->requested_days, 2, '.', ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(LeaveRequest $leaveRequest): array
    {
        $leaveRequest->loadMissing(['employee', 'leaveType']);

        return [
            'leave_request_id' => $leaveRequest->getKey(),
            'employee_name' => $leaveRequest->employee?->full_name,
            'leave_type' => $leaveRequest->leaveType?->name,
            'start_date' => $leaveRequest->start_date->toDateString(),
            'end_date' => $leaveRequest->end_date->toDateString(),
            'requested_days' => (float) $leaveRequest->requested_days,
            'reason' => $leaveRequest->reason,
        ];
    }
}
