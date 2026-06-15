<?php

namespace App\Services;

use App\Enums\ApprovalModuleType;
use App\Models\ApprovalWorkflow;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ApprovalWorkflowResolverService
{
    public function resolveWorkflow(
        string|ApprovalModuleType $moduleType,
        ?int $companyId,
        ?int $companyGroupId,
        CarbonInterface|string|null $effectiveDate = null,
    ): ?ApprovalWorkflow {
        $effectiveDate = $effectiveDate
            ? Carbon::parse($effectiveDate)->toDateString()
            : now()->toDateString();
        $moduleTypeValue = $moduleType instanceof ApprovalModuleType ? $moduleType->value : $moduleType;
        $companyPriorityId = filled($companyId) ? (int) $companyId : 0;
        $groupPriorityId = filled($companyGroupId) ? (int) $companyGroupId : 0;

        return ApprovalWorkflow::query()
            ->with('steps')
            ->active()
            ->where('module_type', $moduleTypeValue)
            ->where(function ($query) use ($effectiveDate): void {
                $query->whereNull('effective_start_date')
                    ->orWhereDate('effective_start_date', '<=', $effectiveDate);
            })
            ->where(function ($query) use ($effectiveDate): void {
                $query->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $effectiveDate);
            })
            ->where(function ($query) use ($companyId, $companyGroupId): void {
                if (filled($companyId)) {
                    $query->orWhere('company_id', $companyId);
                }

                if (filled($companyGroupId)) {
                    $query->orWhere(function ($groupQuery) use ($companyGroupId): void {
                        $groupQuery->whereNull('company_id')
                            ->where('company_group_id', $companyGroupId);
                    });
                }

                $query->orWhere(function ($globalQuery): void {
                    $globalQuery->whereNull('company_id')
                        ->whereNull('company_group_id');
                });
            })
            ->orderByRaw(
                'case when company_id = ? then 1 when company_id is null and company_group_id = ? then 2 else 3 end',
                [$companyPriorityId, $groupPriorityId],
            )
            ->orderByDesc('effective_start_date')
            ->orderBy('id')
            ->first();
    }
}
