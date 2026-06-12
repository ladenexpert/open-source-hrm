<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'max_employees',
        'is_active',
    ];

    protected $casts = [
        'max_employees' => 'integer',
        'is_active' => 'boolean',
    ];

    public function companySubscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }
}
