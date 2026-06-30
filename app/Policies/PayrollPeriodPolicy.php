<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\PayrollPeriod;

class PayrollPeriodPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function view(Employee $user, PayrollPeriod $payrollPeriod): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollPeriod);
    }

    public function create(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function update(Employee $user, PayrollPeriod $payrollPeriod): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollPeriod);
    }

    public function delete(Employee $user, PayrollPeriod $payrollPeriod): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollPeriod);
    }
}
