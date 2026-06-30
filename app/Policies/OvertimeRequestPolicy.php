<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\ApprovalActionService;

class OvertimeRequestPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, OvertimeRequest $overtimeRequest): bool
    {
        return $this->canManageCompanyHrRecord($user, $overtimeRequest)
            || (
                $this->isActiveUser($user)
                && $this->isOwnEmployeeRecord($user, $overtimeRequest->employee_id)
                && $this->sharesCompany($user, $overtimeRequest)
            );
    }

    public function create(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function update(Employee $user, OvertimeRequest $overtimeRequest): bool
    {
        return $this->isActiveUser($user)
            && $overtimeRequest->isDraft()
            && (
                (
                    $this->isOwnEmployeeRecord($user, $overtimeRequest->employee_id)
                    && $this->sharesCompany($user, $overtimeRequest)
                )
                || $this->canManageCompanyHrRecord($user, $overtimeRequest)
            );
    }

    public function delete(Employee $user, OvertimeRequest $overtimeRequest): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }

    public function submit(Employee $user, OvertimeRequest $overtimeRequest): bool
    {
        return $this->isActiveUser($user)
            && $overtimeRequest->isDraft()
            && (
                (
                    $this->isOwnEmployeeRecord($user, $overtimeRequest->employee_id)
                    && $this->sharesCompany($user, $overtimeRequest)
                )
                || $this->canManageCompanyHrRecord($user, $overtimeRequest)
            );
    }

    public function cancel(Employee $user, OvertimeRequest $overtimeRequest): bool
    {
        return $this->isActiveUser($user)
            && $overtimeRequest->canCancel()
            && (
                (
                    $this->isOwnEmployeeRecord($user, $overtimeRequest->employee_id)
                    && $this->sharesCompany($user, $overtimeRequest)
                )
                || $this->canManageCompanyHrRecord($user, $overtimeRequest)
            );
    }

    public function approve(Employee $user, OvertimeRequest $overtimeRequest): bool
    {
        $approvalRequest = $overtimeRequest->approvalRequest()->first();

        return $this->isActiveUser($user)
            && (
                ($approvalRequest !== null && app(ApprovalActionService::class)->canApprove($approvalRequest, $user))
                || ($approvalRequest === null && $this->canManageCompanyHrRecord($user, $overtimeRequest))
            );
    }

    public function reject(Employee $user, OvertimeRequest $overtimeRequest): bool
    {
        $approvalRequest = $overtimeRequest->approvalRequest()->first();

        return $this->isActiveUser($user)
            && (
                ($approvalRequest !== null && app(ApprovalActionService::class)->canReject($approvalRequest, $user))
                || ($approvalRequest === null && $this->canManageCompanyHrRecord($user, $overtimeRequest))
            );
    }

    public function calculate(Employee $user, OvertimeRequest $overtimeRequest): bool
    {
        return $this->canManageCompanyHrRecord($user, $overtimeRequest)
            && $overtimeRequest->isApproved();
    }
}
