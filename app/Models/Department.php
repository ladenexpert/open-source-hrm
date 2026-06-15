<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Department extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'company_group_id',
        'name',
        'code',
        'description',
        'manager_id',
        'branch_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'company_group_id' => 'integer',
        'manager_id' => 'integer',
        'branch_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $department): void {
            $department->company_id ??= $department->resolveCompanyIdForCreation();

            if (blank($department->company_id) && filled($department->branch_id)) {
                $department->company_id = Branch::query()
                    ->whereKey($department->branch_id)
                    ->value('company_id');
            }

            $department->company_group_id = Company::query()
                ->whereKey($department->company_id)
                ->value('company_group_id');

            if (filled($department->manager_id)) {
                $managerCompanyId = Employee::query()->whereKey($department->manager_id)->value('company_id');

                if (filled($managerCompanyId) && (int) $managerCompanyId !== (int) $department->company_id) {
                    throw ValidationException::withMessages([
                        'manager_id' => 'The selected department manager must belong to the same company.',
                    ]);
                }
            }

            if (filled($department->branch_id)) {
                $branchCompanyId = Branch::query()->whereKey($department->branch_id)->value('company_id');

                if (filled($branchCompanyId) && (int) $branchCompanyId !== (int) $department->company_id) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'The selected branch must belong to the selected company.',
                    ]);
                }
            }
        });
    }

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

    public function companyGroup(): BelongsTo
    {
        return $this->belongsTo(CompanyGroup::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
