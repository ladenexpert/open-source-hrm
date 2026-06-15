<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

abstract class MasterData extends Model
{
    protected $fillable = [
        'company_id',
        'company_group_id',
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'company_group_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $record->syncOrganizationScope();
            $record->validateOrganizationScope();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function companyGroup(): BelongsTo
    {
        return $this->belongsTo(CompanyGroup::class);
    }

    public function scopeVisibleTo(Builder $query, Employee $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $companyIds = $user->accessibleCompanyIds();
        $companyGroupId = $user->getEffectiveCompanyGroupId();
        $qualifiedCompanyColumn = $query->getModel()->qualifyColumn('company_id');
        $qualifiedGroupColumn = $query->getModel()->qualifyColumn('company_group_id');

        return $query->where(function (Builder $scopedQuery) use ($companyGroupId, $companyIds, $qualifiedCompanyColumn, $qualifiedGroupColumn): void {
            $scopedQuery
                ->where(function (Builder $globalQuery) use ($qualifiedCompanyColumn, $qualifiedGroupColumn): void {
                    $globalQuery
                        ->whereNull($qualifiedCompanyColumn)
                        ->whereNull($qualifiedGroupColumn);
                });

            if (filled($companyGroupId)) {
                $scopedQuery->orWhere($qualifiedGroupColumn, $companyGroupId);
            }

            if ($companyIds !== []) {
                $scopedQuery->orWhereIn($qualifiedCompanyColumn, $companyIds);
            }
        });
    }

    public function isAvailableFor(?int $companyId, ?int $companyGroupId): bool
    {
        if (filled($this->company_id)) {
            return filled($companyId) && (int) $this->company_id === (int) $companyId;
        }

        if (filled($this->company_group_id)) {
            return filled($companyGroupId) && (int) $this->company_group_id === (int) $companyGroupId;
        }

        return true;
    }

    protected function syncOrganizationScope(): void
    {
        if (filled($this->company_id)) {
            $this->company_group_id = Company::query()
                ->whereKey($this->company_id)
                ->value('company_group_id');

            return;
        }

        $user = Auth::user();

        if (! $user instanceof Employee) {
            return;
        }

        if (blank($this->company_group_id) && ! $user->isSuperAdmin()) {
            $this->company_group_id = $user->getEffectiveCompanyGroupId();
        }
    }

    protected function validateOrganizationScope(): void
    {
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin() && blank($this->company_id) && blank($this->company_group_id)) {
            throw ValidationException::withMessages([
                'company_group_id' => 'Scoped master data must belong to a company group or a company.',
            ]);
        }

        if (filled($this->company_id) && filled($this->company_group_id)) {
            $companyGroupId = Company::query()
                ->whereKey($this->company_id)
                ->value('company_group_id');

            if (filled($companyGroupId) && (int) $companyGroupId !== (int) $this->company_group_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'The selected company does not belong to the selected company group.',
                ]);
            }
        }
    }
}
