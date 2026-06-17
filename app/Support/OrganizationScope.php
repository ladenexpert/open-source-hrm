<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

class OrganizationScope
{
    public static function applyCompanyScope(Builder $query, Employee $user, string $companyColumn = 'company_id'): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $companyIds = $user->accessibleCompanyIds();

        if ($companyIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->getModel()->qualifyColumn($companyColumn), $companyIds);
    }

    public static function applyCompanyOrGroupScope(
        Builder $query,
        Employee $user,
        string $companyColumn = 'company_id',
        string $companyGroupColumn = 'company_group_id',
    ): Builder {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $companyIds = $user->accessibleCompanyIds();

        if ($companyIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $qualifiedCompanyColumn = $query->getModel()->qualifyColumn($companyColumn);

        if (! $user->canAccessCompanyGroup($user->getEffectiveCompanyGroupId())) {
            return $query->whereIn($qualifiedCompanyColumn, $companyIds);
        }

        $companyGroupId = $user->getEffectiveCompanyGroupId();
        $qualifiedGroupColumn = $query->getModel()->qualifyColumn($companyGroupColumn);

        return $query->where(function (Builder $scope) use ($companyGroupId, $companyIds, $qualifiedCompanyColumn, $qualifiedGroupColumn): void {
            $scope->whereIn($qualifiedCompanyColumn, $companyIds);

            if (filled($companyGroupId)) {
                $scope->orWhere($qualifiedGroupColumn, $companyGroupId);
            }
        });
    }

    public static function applyMasterDataScope(Builder $query, Employee $user): Builder
    {
        return $query->visibleTo($user);
    }
}
