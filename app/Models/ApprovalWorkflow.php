<?php

namespace App\Models;

use App\Enums\ApprovalModuleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalWorkflow extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_group_id',
        'company_id',
        'code',
        'name',
        'module_type',
        'description',
        'is_active',
        'effective_start_date',
        'effective_end_date',
    ];

    protected $casts = [
        'company_group_id' => 'integer',
        'company_id' => 'integer',
        'module_type' => ApprovalModuleType::class,
        'is_active' => 'boolean',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
    ];

    public function companyGroup(): BelongsTo
    {
        return $this->belongsTo(CompanyGroup::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class)
            ->orderBy('step_order')
            ->orderBy('id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
