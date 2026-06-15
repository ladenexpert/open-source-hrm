<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkdayPattern extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $workdayPattern): void {
            if (! $workdayPattern->is_default || blank($workdayPattern->company_id)) {
                return;
            }

            static::query()
                ->where('company_id', $workdayPattern->company_id)
                ->whereKeyNot($workdayPattern->id)
                ->update(['is_default' => false]);
        });
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkdayPatternDay::class)->orderBy('day_of_week')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
