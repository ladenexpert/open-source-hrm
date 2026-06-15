<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::creating(function (Model $model): void {
            if (filled($model->getAttribute('company_id'))) {
                return;
            }

            $companyId = method_exists($model, 'resolveCompanyIdForCreation')
                ? $model->resolveCompanyIdForCreation()
                : null;

            $model->setAttribute('company_id', $companyId ?: static::resolveAuthenticatedCompanyId());
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, Company|int|null $companyId): Builder
    {
        $resolvedCompanyId = $companyId instanceof Company ? $companyId->getKey() : $companyId;

        if (blank($resolvedCompanyId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('company_id'), $resolvedCompanyId);
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        $companyIds = array_values(array_filter(array_map(
            static fn ($companyId): ?int => filled($companyId) ? (int) $companyId : null,
            $companyIds,
        )));

        if ($companyIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($this->qualifyColumn('company_id'), $companyIds);
    }

    public function belongsToCompany(Company|int|null $companyId): bool
    {
        $resolvedCompanyId = $companyId instanceof Company ? $companyId->getKey() : $companyId;

        return filled($resolvedCompanyId)
            && (int) $this->getAttribute('company_id') === (int) $resolvedCompanyId;
    }

    protected static function resolveAuthenticatedCompanyId(): ?int
    {
        $user = Auth::user();

        if ($user instanceof Employee) {
            return $user->getEffectiveCompanyId();
        }

        return Company::getDefaultCompanyId();
    }
}
