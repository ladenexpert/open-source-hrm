<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkLocation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'address',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_active' => 'boolean',
        'branch_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $workLocation): void {
            if (blank($workLocation->branch_id)) {
                return;
            }

            $branchCompanyId = Branch::query()->whereKey($workLocation->branch_id)->value('company_id');

            if (filled($branchCompanyId) && filled($workLocation->company_id) && (int) $branchCompanyId !== (int) $workLocation->company_id) {
                throw ValidationException::withMessages([
                    'branch_id' => 'The selected branch must belong to the selected company.',
                ]);
            }
        });
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->branch_id)) {
            return Branch::query()->whereKey($this->branch_id)->value('company_id');
        }

        return null;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
