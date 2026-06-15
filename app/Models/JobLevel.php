<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class JobLevel extends MasterData
{
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function leavePolicies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }
}
