<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\PayrollRunEmployee;

class PayrollRunEmployeePolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function view(Employee $user, PayrollRunEmployee $payrollRunEmployee): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRunEmployee);
    }

    public function update(Employee $user, PayrollRunEmployee $payrollRunEmployee): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRunEmployee);
    }
}
