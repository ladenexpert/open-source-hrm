<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;

class LeaveRequestAttachmentPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, LeaveRequestAttachment $leaveRequestAttachment): bool
    {
        if ($this->canManageCompanyHrRecord($user, $leaveRequestAttachment)) {
            return true;
        }

        $leaveRequest = $leaveRequestAttachment->relationLoaded('leaveRequest')
            ? $leaveRequestAttachment->leaveRequest
            : $leaveRequestAttachment->leaveRequest()->first();

        return $leaveRequest instanceof LeaveRequest
            && $this->sharesCompany($user, $leaveRequestAttachment)
            && $this->isOwnEmployeeRecord($user, $leaveRequest->employee_id);
    }

    public function create(Employee $user): bool
    {
        return false;
    }

    public function update(Employee $user, LeaveRequestAttachment $leaveRequestAttachment): bool
    {
        return false;
    }

    public function delete(Employee $user, LeaveRequestAttachment $leaveRequestAttachment): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }
}
