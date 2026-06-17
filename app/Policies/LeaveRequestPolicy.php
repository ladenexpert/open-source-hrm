<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\ApprovalActionService;

class LeaveRequestPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, LeaveRequest $leaveRequest): bool
    {
        return $this->canManageCompanyHrRecord($user, $leaveRequest)
            || $this->canAccessEmployeeOwnedRecord($user, $leaveRequest);
    }

    public function create(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function update(Employee $user, LeaveRequest $leaveRequest): bool
    {
        return $this->isActiveUser($user)
            && $leaveRequest->isEditable()
            && $this->isOwnEmployeeRecord($user, $leaveRequest->employee_id)
            && $this->sharesCompany($user, $leaveRequest);
    }

    public function delete(Employee $user, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }

    public function cancel(Employee $user, LeaveRequest $leaveRequest): bool
    {
        return $this->isActiveUser($user)
            && $leaveRequest->isCancellable()
            && $this->isOwnEmployeeRecord($user, $leaveRequest->employee_id)
            && $this->sharesCompany($user, $leaveRequest);
    }

    public function approve(Employee $user, LeaveRequest $leaveRequest): bool
    {
        $approvalRequest = $leaveRequest->approvalRequest()->first();

        return $this->isActiveUser($user)
            && $approvalRequest !== null
            && app(ApprovalActionService::class)->canApprove($approvalRequest, $user);
    }

    public function reject(Employee $user, LeaveRequest $leaveRequest): bool
    {
        $approvalRequest = $leaveRequest->approvalRequest()->first();

        return $this->isActiveUser($user)
            && $approvalRequest !== null
            && app(ApprovalActionService::class)->canReject($approvalRequest, $user);
    }

    public function cancelApproved(Employee $user, LeaveRequest $leaveRequest): bool
    {
        return $this->isActiveUser($user)
            && $leaveRequest->isApproved()
            && $this->canManageCompanyHrRecord($user, $leaveRequest);
    }
}
