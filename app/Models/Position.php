<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'title',
        'department_id',
        'code',
        'description',
        'salary',
    ];

    protected $table = 'positions';

    protected $with = ['department'];

    protected $casts = [
        'company_id' => 'integer',
        'salary' => 'decimal:2',
    ];

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->department_id)) {
            return Department::query()->whereKey($this->department_id)->value('company_id');
        }

        return null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
