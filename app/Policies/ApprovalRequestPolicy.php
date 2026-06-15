<?php

namespace App\Policies;

use App\Enums\ApprovalModuleType;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Services\ApprovalActionService;
use App\Support\ApprovalRoleMap;

class ApprovalRequestPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, ApprovalRequest $approvalRequest): bool
    {
        if (! $this->isActiveUser($user)) {
            return false;
        }

        if ((int) $approvalRequest->requester_id === (int) $user->getKey()) {
            return true;
        }

        if ($approvalRequest->isAssignedTo($user)) {
            return true;
        }

        if (
            ApprovalRoleMap::matches($user, ApprovalRoleMap::workflowManagerRoles())
            && $this->sharesCompanyOrGroupScope($user, $approvalRequest)
        ) {
            return true;
        }

        if (
            ApprovalRoleMap::matches($user, ApprovalRoleMap::financeRoles())
            && $this->sharesCompany($user, $approvalRequest)
            && in_array(
                $approvalRequest->module_type,
                [ApprovalModuleType::PAYROLL, ApprovalModuleType::SALARY_CHANGE],
                true,
            )
        ) {
            return true;
        }

        return false;
    }

    public function approve(Employee $user, ApprovalRequest $approvalRequest): bool
    {
        return app(ApprovalActionService::class)->canApprove($approvalRequest, $user);
    }

    public function reject(Employee $user, ApprovalRequest $approvalRequest): bool
    {
        return app(ApprovalActionService::class)->canReject($approvalRequest, $user);
    }

    public function cancel(Employee $user, ApprovalRequest $approvalRequest): bool
    {
        return app(ApprovalActionService::class)->canCancel($approvalRequest, $user);
    }

    public function create(Employee $user): bool
    {
        return false;
    }

    public function update(Employee $user, ApprovalRequest $approvalRequest): bool
    {
        return false;
    }

    public function delete(Employee $user, ApprovalRequest $approvalRequest): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }
}
