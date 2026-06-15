<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'is_paid',
        'requires_attachment',
        'allow_half_day',
        'allow_carry_forward',
        'max_carry_forward_days',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_paid' => 'boolean',
        'requires_attachment' => 'boolean',
        'allow_half_day' => 'boolean',
        'allow_carry_forward' => 'boolean',
        'max_carry_forward_days' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function leavePolicies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    public function leaveTransactions(): HasMany
    {
        return $this->hasMany(LeaveTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
