<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\SubscriptionPlan;

class SubscriptionPlanPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(Employee $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(Employee $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(Employee $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(Employee $user): bool
    {
        return $user->isSuperAdmin();
    }
}
