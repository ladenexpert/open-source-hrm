<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentityType extends MasterData
{
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
