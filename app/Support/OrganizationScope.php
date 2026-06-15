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

    public static function applyMasterDataScope(Builder $query, Employee $user): Builder
    {
        return $query->visibleTo($user);
    }
}
