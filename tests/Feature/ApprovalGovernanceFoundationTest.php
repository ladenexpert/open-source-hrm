<?php

namespace Tests\Feature;

use App\Enums\ApprovalApproverType;
use App\Enums\ApprovalModuleType;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\IdentityType;
use App\Models\JobGrade;
use App\Models\JobLevel;
use App\Models\Leave;
use App\Models\MaritalStatus;
use App\Models\Position;
use App\Models\Religion;
use App\Models\WorkLocation;
use App\Services\ApprovalActionService;
use App\Services\ApprovalRequestService;
use App\Services\ApprovalWorkflowResolverService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApprovalGovernanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_approval_workflow_tables_migrate_successfully(): void
    {
        $this->assertTrue(Schema::hasTable('approval_workflows'));
        $this->assertTrue(Schema::hasTable('approval_workflow_steps'));
        $this->assertTrue(Schema::hasTable('approval_requests'));
        $this->assertTrue(Schema::hasTable('approval_request_steps'));
        $this->assertTrue(Schema::hasTable('approval_logs'));
    }

    public function test_default_workflows_are_seeded(): void
    {
        $defaultGroup = CompanyGroup::query()->where('code', CompanyGroup::DEFAULT_CODE)->firstOrFail();

        $this->assertCount(count(ApprovalModuleType::cases()), ApprovalWorkflow::query()->get());
        $this->assertDatabaseHas('approval_workflows', [
            'company_group_id' => $defaultGroup->id,
            'company_id' => null,
            'code' => 'LEAVE',
            'module_type' => ApprovalModuleType::LEAVE->value,
        ]);
    }

    public function test_workflow_resolves_by_module_type_and_company_specific_takes_priority(): void
    {
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
        $companyGroup = CompanyGroup::query()->where('code', CompanyGroup::DEFAULT_CODE)->firstOrFail();

        $globalWorkflow = ApprovalWorkflow::query()->create([
            'code' => 'CUSTOM-REIMB-GLOBAL',
            'name' => 'Global Reimbursement',
            'module_type' => ApprovalModuleType::REIMBURSEMENT->value,
            'is_active' => true,
        ]);

        $groupWorkflow = ApprovalWorkflow::query()->create([
            'company_group_id' => $companyGroup->id,
            'code' => 'CUSTOM-REIMB-GROUP',
            'name' => 'Group Reimbursement',
            'module_type' => ApprovalModuleType::REIMBURSEMENT->value,
            'is_active' => true,
        ]);

        $companyWorkflow = ApprovalWorkflow::query()->create([
            'company_group_id' => $companyGroup->id,
            'company_id' => $company->id,
            'code' => 'CUSTOM-REIMB-COMPANY',
            'name' => 'Company Reimbursement',
            'module_type' => ApprovalModuleType::REIMBURSEMENT->value,
            'is_active' => true,
        ]);

        $resolver = app(ApprovalWorkflowResolverService::class);
        $resolved = $resolver->resolveWorkflow(ApprovalModuleType::REIMBURSEMENT->value, $company->id, $companyGroup->id);

        $this->assertSame($companyWorkflow->id, $resolved?->id);
        $this->assertNotSame($groupWorkflow->id, $globalWorkflow->id);
    }

    public function test_direct_supervisor_approver_resolves_correctly(): void
    {
        $supervisor = $this->makeEmployee('employee', ['email' => 'supervisor@example.test']);
        $subject = $this->makeEmployee('employee', [
            'email' => 'subject-supervised@example.test',
            'direct_supervisor_id' => $supervisor->id,
        ]);

        $workflow = $this->createWorkflow($subject->company_id, $subject->company_group_id, ApprovalModuleType::OVERTIME->value, [
            ['type' => ApprovalApproverType::DIRECT_SUPERVISOR->value, 'name' => 'Supervisor'],
        ]);

        $resolved = app(ApprovalRequestService::class)->resolveApproversForStep(
            $workflow->steps()->firstOrFail(),
            $subject,
            $subject,
            $subject->company_id,
            $subject->company_group_id,
        );

        $this->assertCount(1, $resolved);
        $this->assertSame($supervisor->id, $resolved->first()->id);
    }

    public function test_department_head_approver_resolves_where_data_exists(): void
    {
        $departmentHead = $this->makeEmployee('department_manager', ['email' => 'dept-head@example.test']);
        $subject = $this->makeEmployee('employee', ['email' => 'dept-subject@example.test']);

        $subject->department()->associate($departmentHead->department)->save();
        $subject->department->update(['manager_id' => $departmentHead->id]);

        $workflow = $this->createWorkflow($subject->company_id, $subject->company_group_id, ApprovalModuleType::MUTATION->value, [
            ['type' => ApprovalApproverType::DEPARTMENT_HEAD->value, 'name' => 'Department Head'],
        ]);

        $resolved = app(ApprovalRequestService::class)->resolveApproversForStep(
            $workflow->steps()->firstOrFail(),
            $subject,
            $subject,
            $subject->company_id,
            $subject->company_group_id,
        );

        $this->assertCount(1, $resolved);
        $this->assertSame($departmentHead->id, $resolved->first()->id);
    }

    public function test_role_based_approver_resolves_correctly(): void
    {
        $financeApprover = $this->makeEmployee('finance', ['email' => 'finance-approver@example.test']);

        $workflow = $this->createWorkflow($financeApprover->company_id, $financeApprover->company_group_id, ApprovalModuleType::PAYROLL->value, [
            [
                'type' => ApprovalApproverType::ROLE->value,
                'name' => 'Finance Role',
                'approver_role' => 'finance',
            ],
        ]);

        $resolved = app(ApprovalRequestService::class)->resolveApproversForStep(
            $workflow->steps()->firstOrFail(),
            $financeApprover,
            $financeApprover,
            $financeApprover->company_id,
            $financeApprover->company_group_id,
        );

        $this->assertTrue($resolved->pluck('id')->contains($financeApprover->id));
    }

    public function test_approval_request_can_be_submitted_and_generates_steps(): void
    {
        [$requester, $supervisor, $finalApprover] = $this->makeSimpleApprovalActors();

        $this->createWorkflow($requester->company_id, $requester->company_group_id, ApprovalModuleType::LEAVE->value, [
            ['type' => ApprovalApproverType::DIRECT_SUPERVISOR->value, 'name' => 'Supervisor Review'],
            [
                'type' => ApprovalApproverType::SPECIFIC_EMPLOYEE->value,
                'name' => 'Final HR Review',
                'approver_employee_id' => $finalApprover->id,
            ],
        ]);

        $leave = $this->makeLeaveRequest($requester);
        $approvalRequest = app(ApprovalRequestService::class)->submit(
            $leave,
            $requester,
            ApprovalModuleType::LEAVE->value,
            $requester,
            'Annual leave request',
            ['days' => 2],
        );

        $this->assertSame(ApprovalRequestStatus::PENDING, $approvalRequest->status);
        $this->assertSame(1, $approvalRequest->current_step_order);
        $this->assertCount(2, $approvalRequest->steps);
        $this->assertSame($supervisor->id, $approvalRequest->steps->firstWhere('step_order', 1)?->approver_id);
    }

    public function test_assigned_approver_can_approve_current_step_and_request_moves_to_next_step(): void
    {
        [$requester, $supervisor, $finalApprover] = $this->makeSimpleApprovalActors();
        $request = $this->submitSimpleApprovalRequest($requester, $supervisor, $finalApprover);

        $updated = app(ApprovalActionService::class)->approveCurrentStep($request, $supervisor, 'Looks good.');

        $this->assertSame(ApprovalRequestStatus::PENDING, $updated->status);
        $this->assertSame(2, $updated->current_step_order);
        $this->assertSame(ApprovalStepStatus::APPROVED, $updated->steps->firstWhere('step_order', 1)?->status);
    }

    public function test_final_approval_marks_request_approved(): void
    {
        [$requester, $supervisor, $finalApprover] = $this->makeSimpleApprovalActors();
        $request = $this->submitSimpleApprovalRequest($requester, $supervisor, $finalApprover);

        $service = app(ApprovalActionService::class);
        $service->approveCurrentStep($request, $supervisor, 'Supervisor approved.');
        $approved = $service->approveCurrentStep($request->fresh(), $finalApprover, 'Final approved.');

        $this->assertSame(ApprovalRequestStatus::APPROVED, $approved->status);
        $this->assertNull($approved->current_step_order);
        $this->assertNotNull($approved->completed_at);
    }

    public function test_reject_marks_request_rejected(): void
    {
        [$requester, $supervisor, $finalApprover] = $this->makeSimpleApprovalActors();
        $request = $this->submitSimpleApprovalRequest($requester, $supervisor, $finalApprover);

        $rejected = app(ApprovalActionService::class)->rejectCurrentStep($request, $supervisor, 'Insufficient detail.');

        $this->assertSame(ApprovalRequestStatus::REJECTED, $rejected->status);
        $this->assertSame(ApprovalStepStatus::REJECTED, $rejected->steps->firstWhere('step_order', 1)?->status);
    }

    public function test_approval_logs_are_created(): void
    {
        [$requester, $supervisor, $finalApprover] = $this->makeSimpleApprovalActors();
        $request = $this->submitSimpleApprovalRequest($requester, $supervisor, $finalApprover);

        app(ApprovalActionService::class)->approveCurrentStep($request, $supervisor, 'Approved.');

        $this->assertSame(2, $request->fresh()->logs()->count());
        $this->assertDatabaseHas('approval_logs', [
            'approval_request_id' => $request->id,
            'action' => 'submitted',
        ]);
        $this->assertDatabaseHas('approval_logs', [
            'approval_request_id' => $request->id,
            'action' => 'approved',
        ]);
    }

    public function test_requester_cannot_approve_own_request(): void
    {
        $requester = $this->makeEmployee('employee', ['email' => 'self-approver@example.test']);

        $request = ApprovalRequest::query()->create([
            'company_group_id' => $requester->company_group_id,
            'company_id' => $requester->company_id,
            'approvable_type' => Leave::class,
            'approvable_id' => $this->makeLeaveRequest($requester)->id,
            'requester_id' => $requester->id,
            'employee_subject_id' => $requester->id,
            'module_type' => ApprovalModuleType::EMPLOYEE_DATA_CHANGE->value,
            'status' => ApprovalRequestStatus::PENDING,
            'current_step_order' => 1,
            'submitted_at' => now(),
        ]);

        $request->steps()->create([
            'step_order' => 1,
            'approver_id' => $requester->id,
            'approver_type' => ApprovalApproverType::SPECIFIC_EMPLOYEE->value,
            'status' => ApprovalStepStatus::PENDING,
        ]);

        $this->expectException(AuthorizationException::class);

        app(ApprovalActionService::class)->approveCurrentStep($request, $requester, 'Self approved.');
    }

    public function test_employee_cannot_manage_workflow_master_data(): void
    {
        $employee = $this->makeEmployee('employee', ['email' => 'workflow-employee@example.test']);

        $this->assertFalse(Gate::forUser($employee)->allows('create', ApprovalWorkflow::class));
    }

    public function test_company_boundary_is_respected_for_approval_actions(): void
    {
        $requester = $this->makeEmployee('employee', ['email' => 'boundary-requester@example.test']);
        $otherGroup = CompanyGroup::query()->create([
            'code' => 'BOUNDARY-GROUP',
            'name' => 'Boundary Group',
            'is_active' => true,
        ]);
        $otherCompany = Company::query()->create([
            'company_group_id' => $otherGroup->id,
            'code' => 'BOUNDARY-CO',
            'name' => 'Boundary Co',
            'company_type' => 'holding',
            'is_legal_entity' => true,
            'is_active' => true,
        ]);
        $outsider = $this->makeEmployee('finance', [
            'company_id' => $otherCompany->id,
            'company_group_id' => $otherGroup->id,
            'email' => 'outsider-approver@example.test',
            'branch_id' => null,
            'work_location_id' => null,
            'department_id' => null,
            'division_id' => null,
            'position_id' => null,
            'job_level_id' => null,
            'job_grade_id' => null,
            'identity_type_id' => null,
            'employment_status_id' => null,
            'employment_type_id' => null,
            'contract_type_id' => null,
            'religion_id' => null,
            'marital_status_id' => null,
            'nik_ktp' => null,
        ]);

        $request = ApprovalRequest::query()->create([
            'company_group_id' => $requester->company_group_id,
            'company_id' => $requester->company_id,
            'approvable_type' => Leave::class,
            'approvable_id' => $this->makeLeaveRequest($requester)->id,
            'requester_id' => $requester->id,
            'employee_subject_id' => $requester->id,
            'module_type' => ApprovalModuleType::PAYROLL->value,
            'status' => ApprovalRequestStatus::PENDING,
            'current_step_order' => 1,
            'submitted_at' => now(),
        ]);

        $request->steps()->create([
            'step_order' => 1,
            'approver_id' => $outsider->id,
            'approver_type' => ApprovalApproverType::SPECIFIC_EMPLOYEE->value,
            'status' => ApprovalStepStatus::PENDING,
        ]);

        $this->expectException(AuthorizationException::class);

        app(ApprovalActionService::class)->approveCurrentStep($request, $outsider, 'Out-of-scope approval.');
    }

    private function submitSimpleApprovalRequest(Employee $requester, Employee $supervisor, Employee $finalApprover): ApprovalRequest
    {
        $this->createWorkflow($requester->company_id, $requester->company_group_id, ApprovalModuleType::LEAVE->value, [
            ['type' => ApprovalApproverType::DIRECT_SUPERVISOR->value, 'name' => 'Supervisor Review'],
            [
                'type' => ApprovalApproverType::SPECIFIC_EMPLOYEE->value,
                'name' => 'Final Review',
                'approver_employee_id' => $finalApprover->id,
            ],
        ]);

        $leave = $this->makeLeaveRequest($requester);

        return app(ApprovalRequestService::class)->submit(
            $leave,
            $requester,
            ApprovalModuleType::LEAVE->value,
            $requester,
            'Simple approval flow',
        );
    }

    private function makeSimpleApprovalActors(): array
    {
        $supervisor = $this->makeEmployee('employee', ['email' => 'flow-supervisor@example.test']);
        $finalApprover = $this->makeEmployee('hr', ['email' => 'flow-final@example.test']);
        $requester = $this->makeEmployee('employee', [
            'email' => 'flow-requester@example.test',
            'direct_supervisor_id' => $supervisor->id,
        ]);

        return [$requester, $supervisor, $finalApprover];
    }

    private function makeLeaveRequest(Employee $requester): Leave
    {
        return Leave::query()->create([
            'company_id' => $requester->company_id,
            'employee_id' => $requester->id,
            'leave_type' => 'Annual Leave',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
            'notes' => 'Need leave for testing',
        ]);
    }

    private function createWorkflow(?int $companyId, ?int $companyGroupId, string $moduleType, array $steps): ApprovalWorkflow
    {
        ApprovalWorkflow::query()
            ->where('company_id', $companyId)
            ->where('company_group_id', $companyGroupId)
            ->where('module_type', $moduleType)
            ->delete();

        $workflow = ApprovalWorkflow::query()->create([
            'company_id' => $companyId,
            'company_group_id' => $companyGroupId,
            'code' => strtoupper($moduleType).'-CUSTOM-'.$this->employeeSequence,
            'name' => ucfirst(str_replace('_', ' ', $moduleType)).' Custom Workflow',
            'module_type' => $moduleType,
            'is_active' => true,
        ]);

        foreach ($steps as $index => $step) {
            $workflow->steps()->create([
                'step_order' => $index + 1,
                'name' => $step['name'],
                'approver_type' => $step['type'],
                'approver_role' => $step['approver_role'] ?? null,
                'approver_employee_id' => $step['approver_employee_id'] ?? null,
                'approver_job_level_id' => $step['approver_job_level_id'] ?? null,
                'is_required' => true,
                'can_reject' => true,
                'can_return' => false,
                'is_final_step' => (bool) ($step['is_final_step'] ?? false),
            ]);
        }

        return $workflow->load('steps');
    }

    private function makeEmployee(string $role, array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;
        $companyId = $attributes['company_id'] ?? Company::query()->where('code', Company::DEFAULT_CODE)->value('id');
        $company = Company::query()->findOrFail($companyId);
        $branch = Branch::query()->where('company_id', $company->id)->first();
        $workLocation = WorkLocation::query()->where('company_id', $company->id)->first();
        $department = Department::query()->where('company_id', $company->id)->orderBy('id')->first();
        $division = $department
            ? Division::query()->where('department_id', $department->id)->orderBy('id')->first()
            : null;
        $position = Position::query()
            ->where('company_id', $company->id)
            ->when($department, fn ($query) => $query->where('department_id', $department->id))
            ->when($division, fn ($query) => $query->where('division_id', $division->id))
            ->orderBy('id')
            ->first();

        $employee = Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-A-%03d', $sequence),
            'full_name' => "Approval Employee {$sequence}",
            'first_name' => 'Approval',
            'last_name' => "Employee {$sequence}",
            'email' => sprintf('approval-employee-%03d@example.test', $sequence),
            'phone' => sprintf('08124000%04d', $sequence),
            'company_id' => $company->id,
            'company_group_id' => $attributes['company_group_id'] ?? $company->company_group_id,
            'branch_id' => $branch?->id,
            'work_location_id' => $workLocation?->id,
            'department_id' => $department?->id,
            'division_id' => $division?->id,
            'position_id' => $position?->id,
            'job_level_id' => JobLevel::query()->where('code', 'L1')->value('id'),
            'job_grade_id' => JobGrade::query()->where('code', 'G1')->value('id'),
            'identity_type_id' => IdentityType::query()->where('code', 'KTP')->value('id'),
            'nik_ktp' => sprintf('557401010190%04d', $sequence),
            'employment_status_id' => EmploymentStatus::query()->where('code', 'ACTIVE')->value('id'),
            'employment_type_id' => EmploymentType::query()->where('code', 'PKWTT')->value('id'),
            'contract_type_id' => ContractType::query()->where('code', 'PERMANENT')->value('id'),
            'religion_id' => Religion::query()->where('code', 'ISLAM')->value('id'),
            'marital_status_id' => MaritalStatus::query()->where('code', 'SINGLE')->value('id'),
            'join_date' => now()->toDateString(),
            'hire_date' => now()->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->syncRoles([$role]);

        return $employee;
    }
}
