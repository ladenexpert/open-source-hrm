<?php

namespace App\Policies;

use App\Models\AttendancePolicy;
use App\Models\Employee;

class AttendancePolicyPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, AttendancePolicy $attendancePolicy): bool
    {
        return $this->canManageCompanyHrRecord($user, $attendancePolicy);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, AttendancePolicy $attendancePolicy): bool
    {
        return $this->canManageCompanyHrRecord($user, $attendancePolicy);
    }

    public function delete(Employee $user, AttendancePolicy $attendancePolicy): bool
    {
        return $this->canManageCompanyHrRecord($user, $attendancePolicy);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
