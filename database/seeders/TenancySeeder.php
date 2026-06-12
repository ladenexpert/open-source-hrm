<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\SubscriptionPlan;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;

class TenancySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::findOrCreateDefault();

        Employee::query()
            ->whereNull('company_id')
            ->update(['company_id' => $company->id]);

        $branch = Branch::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'HQ',
            ],
            [
                'name' => 'Default Branch',
                'is_active' => true,
            ]
        );

        $workLocation = WorkLocation::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'HQ-LOC',
            ],
            [
                'branch_id' => $branch->id,
                'name' => 'Default Work Location',
                'is_active' => true,
            ]
        );

        $costCenter = CostCenter::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'GENERAL',
            ],
            [
                'name' => 'Default Cost Center',
                'description' => 'Starter cost center for legacy and local records.',
                'is_active' => true,
            ]
        );

        Employee::query()
            ->where('company_id', $company->id)
            ->whereNull('branch_id')
            ->update([
                'branch_id' => $branch->id,
                'work_location_id' => $workLocation->id,
                'cost_center_id' => $costCenter->id,
            ]);

        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['code' => 'STARTER'],
            [
                'name' => 'Starter',
                'description' => 'Starter subscription for local single-database tenancy.',
                'max_employees' => 50,
                'is_active' => true,
            ]
        );

        CompanySubscription::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => now()->toDateString(),
            ],
            [
                'status' => 'active',
                'end_date' => null,
            ]
        );
    }
}
