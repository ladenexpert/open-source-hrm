<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploymentStatus extends MasterData
{
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function leavePolicies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }
}
