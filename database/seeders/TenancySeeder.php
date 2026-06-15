<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyGroup;
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
        $companyGroup = CompanyGroup::findOrCreateDefault();

        $company = Company::findOrCreateDefault();
        $company->forceFill([
            'company_group_id' => $companyGroup->id,
            'company_type' => 'holding',
            'is_legal_entity' => true,
        ])->save();

        $subsidiaryA = Company::query()->updateOrCreate(
            ['code' => 'SUB-A'],
            [
                'company_group_id' => $companyGroup->id,
                'parent_company_id' => $company->id,
                'name' => 'Example Subsidiary Company A',
                'legal_name' => 'Example Subsidiary Company A',
                'company_type' => 'subsidiary',
                'is_legal_entity' => true,
                'is_active' => true,
            ],
        );

        $subsidiaryB = Company::query()->updateOrCreate(
            ['code' => 'SUB-B'],
            [
                'company_group_id' => $companyGroup->id,
                'parent_company_id' => $company->id,
                'name' => 'Example Subsidiary Company B',
                'legal_name' => 'Example Subsidiary Company B',
                'company_type' => 'subsidiary',
                'is_legal_entity' => true,
                'is_active' => true,
            ],
        );

        Employee::query()
            ->whereNull('company_id')
            ->update([
                'company_id' => $company->id,
                'company_group_id' => $companyGroup->id,
            ]);

        Employee::query()
            ->whereNull('company_group_id')
            ->update(['company_group_id' => $companyGroup->id]);

        [$branch, $workLocation, $costCenter] = $this->seedCompanyInfrastructure(
            $company,
            'HQ',
            'Default Branch',
            'HQ-LOC',
            'Default Work Location',
            'GENERAL',
            'Default Cost Center',
        );

        $this->seedCompanyInfrastructure(
            $subsidiaryA,
            'SUBA-HQ',
            'Subsidiary A Branch',
            'SUBA-LOC',
            'Subsidiary A Work Location',
            'SUBA-GEN',
            'Subsidiary A Cost Center',
        );

        $this->seedCompanyInfrastructure(
            $subsidiaryB,
            'SUBB-HQ',
            'Subsidiary B Branch',
            'SUBB-LOC',
            'Subsidiary B Work Location',
            'SUBB-GEN',
            'Subsidiary B Cost Center',
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

    private function seedCompanyInfrastructure(
        Company $company,
        string $branchCode,
        string $branchName,
        string $workLocationCode,
        string $workLocationName,
        string $costCenterCode,
        string $costCenterName,
    ): array {
        $branch = Branch::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => $branchCode,
            ],
            [
                'name' => $branchName,
                'is_active' => true,
            ]
        );

        $workLocation = WorkLocation::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => $workLocationCode,
            ],
            [
                'branch_id' => $branch->id,
                'name' => $workLocationName,
                'is_active' => true,
            ]
        );

        $costCenter = CostCenter::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => $costCenterCode,
            ],
            [
                'name' => $costCenterName,
                'description' => "Starter cost center for {$company->name}.",
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

        return [$branch, $workLocation, $costCenter];
    }
}
