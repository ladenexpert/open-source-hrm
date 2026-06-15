<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MaritalStatus extends MasterData
{
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
