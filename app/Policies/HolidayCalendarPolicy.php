<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\HolidayCalendar;

class HolidayCalendarPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, HolidayCalendar $holidayCalendar): bool
    {
        return $this->canManageCompanyHrRecord($user, $holidayCalendar);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, HolidayCalendar $holidayCalendar): bool
    {
        return $this->canManageCompanyHrRecord($user, $holidayCalendar);
    }

    public function delete(Employee $user, HolidayCalendar $holidayCalendar): bool
    {
        return $this->canManageCompanyHrRecord($user, $holidayCalendar);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
