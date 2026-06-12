<?php

namespace App\Policies;

use App\Models\CompanySubscription;
use App\Models\Employee;

class CompanySubscriptionPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(Employee $user, CompanySubscription $companySubscription): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(Employee $user, CompanySubscription $companySubscription): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(Employee $user, CompanySubscription $companySubscription): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }
}
