<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Payroll;

class PayrollPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function view(Employee $user, Payroll $payroll): bool
    {
        return $this->canManagePayroll($user)
            || $this->isOwnEmployeeRecord($user, $payroll->employee_id);
    }

    public function create(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function update(Employee $user, Payroll $payroll): bool
    {
        return $this->canManagePayroll($user);
    }

    public function delete(Employee $user, Payroll $payroll): bool
    {
        return $this->canManagePayroll($user);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }
}
