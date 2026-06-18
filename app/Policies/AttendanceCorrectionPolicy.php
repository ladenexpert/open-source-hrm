<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Services\ApprovalActionService;

class AttendanceCorrectionPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, AttendanceCorrection $attendanceCorrection): bool
    {
        return $this->canManageCompanyHrRecord($user, $attendanceCorrection)
            || (
                $this->isActiveUser($user)
                && $this->isOwnEmployeeRecord($user, $attendanceCorrection->employee_id)
                && $this->sharesCompany($user, $attendanceCorrection)
            );
    }

    public function create(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function update(Employee $user, AttendanceCorrection $attendanceCorrection): bool
    {
        return $this->isActiveUser($user)
            && $attendanceCorrection->isDraft()
            && $this->isOwnEmployeeRecord($user, $attendanceCorrection->employee_id)
            && $this->sharesCompany($user, $attendanceCorrection);
    }

    public function delete(Employee $user, AttendanceCorrection $attendanceCorrection): bool
    {
        return false;
    }

    public function deleteAny(Employee $user): bool
    {
        return false;
    }

    public function submit(Employee $user, AttendanceCorrection $attendanceCorrection): bool
    {
        return $this->isActiveUser($user)
            && $attendanceCorrection->isDraft()
            && (
                (
                    $this->isOwnEmployeeRecord($user, $attendanceCorrection->employee_id)
                    && $this->sharesCompany($user, $attendanceCorrection)
                )
                || $this->canManageCompanyHrRecord($user, $attendanceCorrection)
            );
    }

    public function cancel(Employee $user, AttendanceCorrection $attendanceCorrection): bool
    {
        return $this->isActiveUser($user)
            && $attendanceCorrection->canCancel()
            && (
                (
                    $this->isOwnEmployeeRecord($user, $attendanceCorrection->employee_id)
                    && $this->sharesCompany($user, $attendanceCorrection)
                )
                || $this->canManageCompanyHrRecord($user, $attendanceCorrection)
            );
    }

    public function approve(Employee $user, AttendanceCorrection $attendanceCorrection): bool
    {
        $approvalRequest = $attendanceCorrection->approvalRequest()->first();

        return $this->isActiveUser($user)
            && $approvalRequest !== null
            && app(ApprovalActionService::class)->canApprove($approvalRequest, $user);
    }

    public function reject(Employee $user, AttendanceCorrection $attendanceCorrection): bool
    {
        $approvalRequest = $attendanceCorrection->approvalRequest()->first();

        return $this->isActiveUser($user)
            && $approvalRequest !== null
            && app(ApprovalActionService::class)->canReject($approvalRequest, $user);
    }
}
