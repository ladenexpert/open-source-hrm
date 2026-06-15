<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HolidayCalendar extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'year',
        'description',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class)->orderBy('date')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
