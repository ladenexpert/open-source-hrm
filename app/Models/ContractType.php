<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractType extends MasterData
{
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
