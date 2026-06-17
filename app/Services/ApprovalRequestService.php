<?php

namespace App\Services;

use App\Enums\ApprovalApproverType;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Leave;
use App\Support\ApprovalRoleMap;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalRequestService
{
    public function __construct(
        private readonly ApprovalWorkflowResolverService $workflowResolver,
        private readonly OrganizationAuthorityService $organizationAuthorityService,
    ) {
    }

    public function submit(
        Model $approvable,
        Employee $requester,
        string $moduleType,
        ?Employee $employeeSubject = null,
        ?string $summary = null,
        ?array $payload = null,
    ): ApprovalRequest {
        $subject = $employeeSubject ?: $this->resolveSubjectEmployee($approvable, $requester);
        $companyId = $this->resolveCompanyId($approvable, $subject, $requester);
        $companyGroupId = $this->resolveCompanyGroupId($approvable, $subject, $companyId, $requester);

        if (! $requester->isSuperAdmin() && ! $requester->canAccessCompany($companyId)) {
            throw new RuntimeException('The requester cannot submit approval requests outside their company scope.');
        }

        $workflow = $this->workflowResolver->resolveWorkflow($moduleType, $companyId, $companyGroupId);

        if (! $workflow instanceof ApprovalWorkflow) {
            throw new RuntimeException("No active approval workflow is configured for module [{$moduleType}].");
        }

        $workflow->loadMissing('steps');

        if ($workflow->steps->isEmpty()) {
            throw new RuntimeException('The selected approval workflow does not have any approval steps.');
        }

        return DB::transaction(function () use ($approvable, $requester, $subject, $summary, $payload, $workflow, $companyId, $companyGroupId): ApprovalRequest {
            $request = ApprovalRequest::query()->create([
                'company_group_id' => $companyGroupId,
                'company_id' => $companyId,
                'approval_workflow_id' => $workflow->getKey(),
                'approvable_type' => $approvable::class,
                'approvable_id' => $approvable->getKey(),
                'requester_id' => $requester->getKey(),
                'employee_subject_id' => $subject?->getKey(),
                'module_type' => $workflow->module_type->value,
                'status' => ApprovalRequestStatus::PENDING,
                'submitted_at' => now(),
                'summary' => $summary,
                'payload' => $payload,
            ]);

            $unresolvedStepOrders = [];

            foreach ($workflow->steps as $workflowStep) {
                $approvers = $this->resolveApproversForStep(
                    $workflowStep,
                    $subject,
                    $requester,
                    $companyId,
                    $companyGroupId,
                )
                    ->reject(fn (Employee $approver): bool => (int) $approver->getKey() === (int) $requester->getKey())
                    ->unique('id')
                    ->values();

                if ($approvers->isEmpty()) {
                    $request->steps()->create([
                        'approval_workflow_step_id' => $workflowStep->getKey(),
                        'step_order' => $workflowStep->step_order,
                        'approver_type' => $workflowStep->approver_type->value,
                        'status' => ApprovalStepStatus::PENDING,
                        'comments' => 'Approver resolution is pending because no matching approver was found.',
                    ]);

                    $unresolvedStepOrders[] = $workflowStep->step_order;

                    continue;
                }

                foreach ($approvers as $approver) {
                    $request->steps()->create([
                        'approval_workflow_step_id' => $workflowStep->getKey(),
                        'step_order' => $workflowStep->step_order,
                        'approver_id' => $approver->getKey(),
                        'approver_type' => $workflowStep->approver_type->value,
                        'status' => ApprovalStepStatus::PENDING,
                    ]);
                }
            }

            $currentStepOrder = $request->steps()
                ->where('status', ApprovalStepStatus::PENDING)
                ->min('step_order');

            $request->forceFill([
                'current_step_order' => $currentStepOrder,
            ])->save();

            $request->logs()->create([
                'actor_id' => $requester->getKey(),
                'action' => 'submitted',
                'comments' => $summary,
                'metadata' => [
                    'workflow_id' => $workflow->getKey(),
                    'current_step_order' => $currentStepOrder,
                    'unresolved_step_orders' => $unresolvedStepOrders,
                ],
            ]);

            return $request->load([
                'workflow.steps',
                'steps.workflowStep',
                'steps.approver',
                'logs.actor',
                'requester',
                'employeeSubject',
            ]);
        });
    }

    public function resolveApproversForStep(
        ApprovalWorkflowStep $workflowStep,
        ?Employee $subject,
        Employee $requester,
        ?int $companyId,
        ?int $companyGroupId,
    ): Collection {
        $subject ??= $requester;

        return match ($workflowStep->approver_type) {
            ApprovalApproverType::DIRECT_SUPERVISOR => $this->collectSingleEmployee(
                $subject ? $this->organizationAuthorityService->getDirectSupervisor($subject) : null,
            ),
            ApprovalApproverType::DEPARTMENT_HEAD => $this->collectSingleEmployee(
                $subject ? $this->organizationAuthorityService->getDepartmentHead($subject) : null,
            ),
            ApprovalApproverType::SPECIFIC_EMPLOYEE => $this->resolveSpecificEmployeeApprover($workflowStep, $companyId, $companyGroupId),
            ApprovalApproverType::JOB_LEVEL => $this->resolveJobLevelApprovers($workflowStep, $companyId, $companyGroupId),
            ApprovalApproverType::ROLE => $this->resolveRoleApprovers(
                ApprovalRoleMap::aliasesFor((string) $workflowStep->approver_role),
                $companyId,
                $companyGroupId,
            ),
            ApprovalApproverType::HR_HEAD => $this->resolveRoleApprovers(
                ApprovalRoleMap::aliasesFor('hr_head'),
                $companyId,
                $companyGroupId,
            ),
            ApprovalApproverType::FINANCE_HEAD => $this->resolveRoleApprovers(
                ApprovalRoleMap::aliasesFor('finance_head'),
                $companyId,
                $companyGroupId,
            ),
            ApprovalApproverType::COMPANY_HEAD => $this->resolveCompanyHeadApprovers($companyId, $companyGroupId),
        };
    }

    private function resolveSubjectEmployee(Model $approvable, Employee $fallbackRequester): ?Employee
    {
        if ($approvable instanceof Leave) {
            return $approvable->relationLoaded('employee')
                ? $approvable->employee
                : $approvable->employee()->first();
        }

        if (isset($approvable->employee_id) && filled($approvable->employee_id)) {
            return Employee::query()->find($approvable->employee_id);
        }

        return $fallbackRequester;
    }

    private function resolveCompanyId(Model $approvable, ?Employee $subject, Employee $requester): int
    {
        $companyId = $approvable->getAttribute('company_id')
            ?: $subject?->company_id
            ?: $requester->getEffectiveCompanyId()
            ?: Company::getDefaultCompanyId();

        if (blank($companyId)) {
            throw new RuntimeException('Unable to resolve the company scope for this approval request.');
        }

        return (int) $companyId;
    }

    private function resolveCompanyGroupId(Model $approvable, ?Employee $subject, int $companyId, Employee $requester): ?int
    {
        $companyGroupId = $approvable->getAttributes()['company_group_id'] ?? null
            ?: $subject?->getEffectiveCompanyGroupId()
            ?: $requester->getEffectiveCompanyGroupId()
            ?: Company::query()->whereKey($companyId)->value('company_group_id');

        return filled($companyGroupId) ? (int) $companyGroupId : null;
    }

    private function collectSingleEmployee(?Employee $employee): Collection
    {
        if (! $employee instanceof Employee || ! $employee->is_active) {
            return new Collection();
        }

        return new Collection([$employee]);
    }

    private function resolveSpecificEmployeeApprover(
        ApprovalWorkflowStep $workflowStep,
        ?int $companyId,
        ?int $companyGroupId,
    ): Collection {
        if (blank($workflowStep->approver_employee_id)) {
            return new Collection();
        }

        $employee = Employee::query()->whereKey($workflowStep->approver_employee_id)->where('is_active', true)->first();

        if (! $employee instanceof Employee || ! $this->isWithinScope($employee, $companyId, $companyGroupId)) {
            return new Collection();
        }

        return new Collection([$employee]);
    }

    private function resolveJobLevelApprovers(
        ApprovalWorkflowStep $workflowStep,
        ?int $companyId,
        ?int $companyGroupId,
    ): Collection {
        if (blank($workflowStep->approver_job_level_id)) {
            return new Collection();
        }

        return $this->scopedEmployeeQuery($companyId, $companyGroupId)
            ->where('job_level_id', $workflowStep->approver_job_level_id)
            ->get();
    }

    private function resolveRoleApprovers(array $roles, ?int $companyId, ?int $companyGroupId): Collection
    {
        return $this->scopedEmployeeQuery($companyId, $companyGroupId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->get();
    }

    private function resolveCompanyHeadApprovers(?int $companyId, ?int $companyGroupId): Collection
    {
        $companyScoped = $this->resolveRoleApprovers(
            ApprovalRoleMap::aliasesFor('company_head'),
            $companyId,
            $companyGroupId,
        );

        if ($companyScoped->isNotEmpty()) {
            return $companyScoped;
        }

        if (blank($companyGroupId)) {
            return new Collection();
        }

        return Employee::query()
            ->where('is_active', true)
            ->where('company_group_id', $companyGroupId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super_admin']))
            ->get();
    }

    private function scopedEmployeeQuery(?int $companyId, ?int $companyGroupId)
    {
        return Employee::query()
            ->where('is_active', true)
            ->when(
                filled($companyId),
                fn ($query) => $query->where('company_id', $companyId),
                fn ($query) => $query->when(
                    filled($companyGroupId),
                    fn ($groupQuery) => $groupQuery->where('company_group_id', $companyGroupId),
                ),
            );
    }

    private function isWithinScope(Employee $employee, ?int $companyId, ?int $companyGroupId): bool
    {
        if (filled($companyId)) {
            return (int) $employee->company_id === (int) $companyId;
        }

        if (filled($companyGroupId)) {
            return (int) $employee->getEffectiveCompanyGroupId() === (int) $companyGroupId;
        }

        return true;
    }
}
