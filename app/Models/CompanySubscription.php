<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySubscription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'subscription_plan_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'subscription_plan_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
