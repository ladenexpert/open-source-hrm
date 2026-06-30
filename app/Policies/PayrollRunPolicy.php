<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\PayrollRun;

class PayrollRunPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function view(Employee $user, PayrollRun $payrollRun): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRun);
    }

    public function create(Employee $user): bool
    {
        return $this->canManagePayroll($user);
    }

    public function update(Employee $user, PayrollRun $payrollRun): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRun);
    }

    public function delete(Employee $user, PayrollRun $payrollRun): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRun);
    }

    public function prepare(Employee $user, PayrollRun $payrollRun): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRun);
    }

    public function lock(Employee $user, PayrollRun $payrollRun): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRun);
    }

    public function approve(Employee $user, PayrollRun $payrollRun): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRun);
    }

    public function cancel(Employee $user, PayrollRun $payrollRun): bool
    {
        return $this->canManageCompanyPayrollRecord($user, $payrollRun);
    }
}
