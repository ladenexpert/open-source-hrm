<?php

namespace Tests\Feature;

use App\Enums\ApprovalModuleType;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Filament\Employee\Resources\LeaveRequests\LeaveRequestResource as PortalLeaveRequestResource;
use App\Filament\Pages\MyApprovalInbox;
use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class AccessScopeHardeningTest extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_can_access_records_across_company_groups_and_companies(): void
    {
        [$companyA, $companyB, $companyC, $groupA, $groupB] = $this->makeOrganizationMatrix();
        $superAdmin = $this->makeEmployee('super_admin', [
            'company_id' => $companyA->id,
            'company_group_id' => $groupA->id,
            'email' => 'super-admin-scope@example.test',
        ]);

        $sameGroupRequest = $this->makeApprovalRequest($companyB, $groupA);
        $otherGroupRequest = $this->makeApprovalRequest($companyC, $groupB);

        $accessibleCompanyIds = $superAdmin->accessibleCompanyIds();

        $this->assertContains($companyA->id, $accessibleCompanyIds);
        $this->assertContains($companyB->id, $accessibleCompanyIds);
        $this->assertContains($companyC->id, $accessibleCompanyIds);
        $this->assertTrue($superAdmin->canAccessCompany($companyC->id));
        $this->assertTrue($superAdmin->canAccessCompanyGroup($groupB->id));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $sameGroupRequest));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $otherGroupRequest));
    }

    public function test_company_group_admin_can_access_only_own_company_group(): void
    {
        [$companyA, $companyB, $companyC, $groupA, $groupB] = $this->makeOrganizationMatrix();
        $groupAdmin = $this->makeEmployee('company_group_admin', [
            'company_id' => $companyA->id,
            'company_group_id' => $groupA->id,
            'email' => 'group-admin-scope@example.test',
        ]);

        $sameGroupRequest = $this->makeApprovalRequest($companyB, $groupA);
        $otherGroupRequest = $this->makeApprovalRequest($companyC, $groupB);

        $this->assertEqualsCanonicalizing(
            [$companyA->id, $companyB->id],
            $groupAdmin->accessibleCompanyIds(),
        );
        $this->assertTrue($groupAdmin->canAccessCompany($companyB->id));
        $this->assertFalse($groupAdmin->canAccessCompany($companyC->id));
        $this->assertTrue($groupAdmin->canAccessCompanyGroup($groupA->id));
        $this->assertFalse($groupAdmin->canAccessCompanyGroup($groupB->id));
        $this->assertTrue(Gate::forUser($groupAdmin)->allows('view', $sameGroupRequest));
        $this->assertFalse(Gate::forUser($groupAdmin)->allows('view', $otherGroupRequest));
    }

    public function test_company_admin_can_access_only_own_company(): void
    {
        [$companyA, $companyB, $companyC, $groupA, $groupB] = $this->makeOrganizationMatrix();
        $companyAdmin = $this->makeEmployee('company_admin', [
            'company_id' => $companyA->id,
            'company_group_id' => $groupA->id,
            'email' => 'company-admin-scope@example.test',
        ]);

        $ownCompanyRequest = $this->makeApprovalRequest($companyA, $groupA);
        $sameGroupOtherCompanyRequest = $this->makeApprovalRequest($companyB, $groupA);
        $otherGroupRequest = $this->makeApprovalRequest($companyC, $groupB);

        $this->assertSame([$companyA->id], $companyAdmin->accessibleCompanyIds());
        $this->assertTrue($companyAdmin->canAccessCompany($companyA->id));
        $this->assertFalse($companyAdmin->canAccessCompany($companyB->id));
        $this->assertFalse($companyAdmin->canAccessCompanyGroup($groupA->id));
        $this->assertTrue(Gate::forUser($companyAdmin)->allows('view', $ownCompanyRequest));
        $this->assertFalse(Gate::forUser($companyAdmin)->allows('view', $sameGroupOtherCompanyRequest));
        $this->assertFalse(Gate::forUser($companyAdmin)->allows('view', $otherGroupRequest));
    }

    public function test_employee_portal_leave_request_resource_is_self_scoped(): void
    {
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->firstOrFail();
        $leaveType = LeaveType::query()->where('company_id', $company->id)->firstOrFail();
        $employee = $this->makeEmployee('employee', [
            'company_id' => $company->id,
            'company_group_id' => $company->company_group_id,
            'email' => 'portal-self@example.test',
        ]);
        $otherEmployee = $this->makeEmployee('employee', [
            'company_id' => $company->id,
            'company_group_id' => $company->company_group_id,
            'email' => 'portal-other@example.test',
        ]);

        $ownRequest = LeaveRequest::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
        ]);
        LeaveRequest::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'leave_type_id' => $leaveType->id,
        ]);

        $this->actingAs($employee);

        $visibleIds = PortalLeaveRequestResource::getEloquentQuery()->pluck('id')->all();

        $this->assertSame([$ownRequest->id], $visibleIds);
    }

    public function test_approval_inbox_allows_group_admin_for_same_group_request(): void
    {
        Filament::setCurrentPanel('admin');

        [$companyA, $companyB, , $groupA] = $this->makeOrganizationMatrix();
        $groupAdmin = $this->makeEmployee('company_group_admin', [
            'company_id' => $companyA->id,
            'company_group_id' => $groupA->id,
            'email' => 'group-admin-inbox@example.test',
        ]);
        $assignedStep = $this->makeApprovalRequest($companyB, $groupA, $groupAdmin)->steps()->firstOrFail();

        Livewire::actingAs($groupAdmin)
            ->test(MyApprovalInbox::class)
            ->assertCanSeeTableRecords([$assignedStep]);
    }

    public function test_approval_inbox_hides_same_group_other_company_request_from_company_admin(): void
    {
        Filament::setCurrentPanel('admin');

        [$companyA, $companyB, , $groupA] = $this->makeOrganizationMatrix();
        $companyAdmin = $this->makeEmployee('company_admin', [
            'company_id' => $companyA->id,
            'company_group_id' => $groupA->id,
            'email' => 'company-admin-inbox@example.test',
        ]);
        $assignedStep = $this->makeApprovalRequest($companyB, $groupA, $companyAdmin)->steps()->firstOrFail();

        Livewire::actingAs($companyAdmin)
            ->test(MyApprovalInbox::class)
            ->assertCanNotSeeTableRecords([$assignedStep]);
    }

    /**
     * @return array{0: Company, 1: Company, 2: Company, 3: CompanyGroup, 4: CompanyGroup}
     */
    private function makeOrganizationMatrix(): array
    {
        $groupA = CompanyGroup::query()->create([
            'code' => 'SCOPE-GROUP-A',
            'name' => 'Scope Group A',
            'legal_name' => 'Scope Group A',
            'is_active' => true,
        ]);
        $groupB = CompanyGroup::query()->create([
            'code' => 'SCOPE-GROUP-B',
            'name' => 'Scope Group B',
            'legal_name' => 'Scope Group B',
            'is_active' => true,
        ]);

        $companyA = $this->makeCompany($groupA, 'SCOPE-A', 'Scope Company A');
        $companyB = $this->makeCompany($groupA, 'SCOPE-B', 'Scope Company B');
        $companyC = $this->makeCompany($groupB, 'SCOPE-C', 'Scope Company C');

        return [$companyA, $companyB, $companyC, $groupA, $groupB];
    }

    private function makeCompany(CompanyGroup $group, string $code, string $name): Company
    {
        return Company::query()->create([
            'company_group_id' => $group->id,
            'code' => $code,
            'name' => $name,
            'legal_name' => $name,
            'company_type' => 'subsidiary',
            'is_legal_entity' => true,
            'is_active' => true,
        ]);
    }

    private function makeApprovalRequest(
        Company $company,
        CompanyGroup $group,
        ?Employee $approver = null,
    ): ApprovalRequest {
        $requester = $this->makeEmployee('employee', [
            'company_id' => $company->id,
            'company_group_id' => $group->id,
            'email' => sprintf('requester-%d@example.test', $this->employeeSequence),
        ]);
        $leave = Leave::query()->create([
            'company_id' => $company->id,
            'employee_id' => $requester->id,
            'leave_type' => 'Annual Leave',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
            'notes' => 'Scope hardening request.',
        ]);

        $request = ApprovalRequest::query()->create([
            'company_group_id' => $group->id,
            'company_id' => $company->id,
            'approval_workflow_id' => null,
            'approvable_type' => Leave::class,
            'approvable_id' => $leave->id,
            'requester_id' => $requester->id,
            'employee_subject_id' => $requester->id,
            'module_type' => ApprovalModuleType::LEAVE->value,
            'status' => ApprovalRequestStatus::PENDING,
            'submitted_at' => now(),
            'current_step_order' => 1,
            'summary' => 'Scope hardening approval request',
        ]);

        if ($approver instanceof Employee) {
            $request->steps()->create([
                'step_order' => 1,
                'approver_id' => $approver->id,
                'approver_type' => 'specific_employee',
                'status' => ApprovalStepStatus::PENDING,
            ]);
        }

        return $request;
    }

    private function makeEmployee(string $role, array $attributes = []): Employee
    {
        $sequence = $this->employeeSequence++;
        $companyId = $attributes['company_id'] ?? Company::query()->where('code', Company::DEFAULT_CODE)->value('id');
        $company = Company::query()->findOrFail($companyId);

        $employee = Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-SCOPE-%03d', $sequence),
            'full_name' => "Scope Employee {$sequence}",
            'first_name' => 'Scope',
            'last_name' => "Employee {$sequence}",
            'email' => sprintf('scope-employee-%03d@example.test', $sequence),
            'company_id' => $company->id,
            'company_group_id' => $attributes['company_group_id'] ?? $company->company_group_id,
            'employment_type' => 'Permanent',
            'hire_date' => now()->toDateString(),
            'join_date' => now()->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->syncRoles([$role]);

        return $employee;
    }
}
