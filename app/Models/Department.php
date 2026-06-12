<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'manager_id',
        'branch_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'manager_id' => 'integer',
        'branch_id' => 'integer',
    ];

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->manager_id)) {
            return Employee::query()->whereKey($this->manager_id)->value('company_id');
        }

        return null;
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }
}
