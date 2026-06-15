<?php

namespace Database\Seeders;

use App\Enums\ApprovalApproverType;
use App\Enums\ApprovalModuleType;
use App\Models\ApprovalWorkflow;
use App\Models\CompanyGroup;
use Illuminate\Database\Seeder;

class ApprovalWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $defaultGroup = CompanyGroup::findOrCreateDefault();

        $definitions = [
            ApprovalModuleType::LEAVE->value => [
                ['name' => 'Direct Supervisor Review', 'type' => ApprovalApproverType::DIRECT_SUPERVISOR->value],
                ['name' => 'Department Head Review', 'type' => ApprovalApproverType::DEPARTMENT_HEAD->value],
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::ATTENDANCE_CORRECTION->value => [
                ['name' => 'Direct Supervisor Review', 'type' => ApprovalApproverType::DIRECT_SUPERVISOR->value],
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::OVERTIME->value => [
                ['name' => 'Direct Supervisor Review', 'type' => ApprovalApproverType::DIRECT_SUPERVISOR->value],
                ['name' => 'Department Head Review', 'type' => ApprovalApproverType::DEPARTMENT_HEAD->value],
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::PAYROLL->value => [
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value],
                ['name' => 'Finance Review', 'type' => ApprovalApproverType::FINANCE_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::SALARY_CHANGE->value => [
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value],
                ['name' => 'Finance Review', 'type' => ApprovalApproverType::FINANCE_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::MUTATION->value => [
                ['name' => 'Department Head Review', 'type' => ApprovalApproverType::DEPARTMENT_HEAD->value],
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value],
                ['name' => 'Company Head Review', 'type' => ApprovalApproverType::COMPANY_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::PROMOTION->value => [
                ['name' => 'Department Head Review', 'type' => ApprovalApproverType::DEPARTMENT_HEAD->value],
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value],
                ['name' => 'Company Head Review', 'type' => ApprovalApproverType::COMPANY_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::DEMOTION->value => [
                ['name' => 'Department Head Review', 'type' => ApprovalApproverType::DEPARTMENT_HEAD->value],
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value],
                ['name' => 'Company Head Review', 'type' => ApprovalApproverType::COMPANY_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::RECRUITMENT->value => [
                ['name' => 'Department Head Review', 'type' => ApprovalApproverType::DEPARTMENT_HEAD->value],
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::APPRAISAL->value => [
                ['name' => 'Direct Supervisor Review', 'type' => ApprovalApproverType::DIRECT_SUPERVISOR->value],
                ['name' => 'Department Head Review', 'type' => ApprovalApproverType::DEPARTMENT_HEAD->value],
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::EMPLOYEE_DATA_CHANGE->value => [
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::REIMBURSEMENT->value => [
                ['name' => 'Direct Supervisor Review', 'type' => ApprovalApproverType::DIRECT_SUPERVISOR->value],
                ['name' => 'Finance Review', 'type' => ApprovalApproverType::FINANCE_HEAD->value, 'final' => true],
            ],
            ApprovalModuleType::LOAN->value => [
                ['name' => 'HR Review', 'type' => ApprovalApproverType::HR_HEAD->value],
                ['name' => 'Finance Review', 'type' => ApprovalApproverType::FINANCE_HEAD->value, 'final' => true],
            ],
        ];

        foreach ($definitions as $moduleType => $steps) {
            $workflow = ApprovalWorkflow::query()->updateOrCreate(
                [
                    'company_group_id' => $defaultGroup->id,
                    'company_id' => null,
                    'code' => strtoupper($moduleType),
                ],
                [
                    'name' => str($moduleType)->replace('_', ' ')->title()->append(' Approval')->value(),
                    'module_type' => $moduleType,
                    'description' => "Default {$moduleType} approval workflow for the company group.",
                    'is_active' => true,
                ],
            );

            $workflow->steps()->delete();

            foreach ($steps as $index => $step) {
                $workflow->steps()->create([
                    'step_order' => $index + 1,
                    'name' => $step['name'],
                    'approver_type' => $step['type'],
                    'is_required' => true,
                    'can_reject' => true,
                    'can_return' => false,
                    'is_final_step' => (bool) ($step['final'] ?? false),
                ]);
            }
        }
    }
}
