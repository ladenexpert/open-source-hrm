<?php

namespace App\Policies;

use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use App\Support\ApprovalRoleMap;

class ApprovalWorkflowPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user)
            && ApprovalRoleMap::matches($user, ApprovalRoleMap::workflowManagerRoles());
    }

    public function view(Employee $user, ApprovalWorkflow $approvalWorkflow): bool
    {
        return $this->viewAny($user) && $this->sharesCompanyOrGroupScope($user, $approvalWorkflow);
    }

    public function create(Employee $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(Employee $user, ApprovalWorkflow $approvalWorkflow): bool
    {
        return $this->view($user, $approvalWorkflow);
    }

    public function delete(Employee $user, ApprovalWorkflow $approvalWorkflow): bool
    {
        return $this->view($user, $approvalWorkflow);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->create($user);
    }
}
