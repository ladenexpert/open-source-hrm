<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Event;

class EventPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, Event $event): bool
    {
        return $this->sharesCompany($user, $event) && $this->isActiveUser($user);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, Event $event): bool
    {
        return $this->canManageCompanyHrRecord($user, $event);
    }

    public function delete(Employee $user, Event $event): bool
    {
        return $this->canManageCompanyHrRecord($user, $event);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
