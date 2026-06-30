<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\PayrollComponent;

class PayrollComponentPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function view(Employee $user, PayrollComponent $payrollComponent): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollComponent);
    }

    public function create(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function update(Employee $user, PayrollComponent $payrollComponent): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollComponent);
    }

    public function delete(Employee $user, PayrollComponent $payrollComponent): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollComponent);
    }
}
