<?php

namespace App\Policies;

use App\Models\ApprovalLog;
use App\Models\Employee;

class ApprovalLogPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user)
            && ($user->canManageHrMasterData() || $user->canManagePayroll());
    }

    public function view(Employee $user, ApprovalLog $approvalLog): bool
    {
        $request = $approvalLog->relationLoaded('request')
            ? $approvalLog->request
            : $approvalLog->request()->first();

        return $request !== null
            && app(ApprovalRequestPolicy::class)->view($user, $request);
    }
}
