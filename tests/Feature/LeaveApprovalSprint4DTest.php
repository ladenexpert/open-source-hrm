<?php

namespace Tests\Feature;

use App\Events\Leave\LeaveRequestApproved;
use App\Events\Leave\LeaveRequestCancelled;
use App\Events\Leave\LeaveRequestRejected;
use App\Events\Leave\LeaveRequestSubmitted;
use App\Filament\Pages\MyApprovalInbox;
use App\Filament\Resources\LeaveRequests\Pages\ViewLeaveRequest as AdminViewLeaveRequest;
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
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\MaritalStatus;
use App\Models\Position;
use App\Models\Religion;
use App\Models\WorkLocation;
use App\Services\Leave\LeaveApprovalService;
use App\Services\LeaveEntitlementService;
use App\Services\LeaveRequestService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LeaveApprovalSprint4DTest extends TestCase
{
    use RefreshDatabase;

    private int $employeeSequence = 1;

    private LeaveRequestService $leaveRequestService;

    private LeaveApprovalService $leaveApprovalService;

    private LeaveEntitlementService $leaveEntitlementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->leaveRequestService = app(LeaveRequestService::class);
        $this->leaveApprovalService = app(LeaveApprovalService::class);
        $this->leaveEntitlementService = app(LeaveEntitlementService::class);
    }

    public function test_submit_creates_approval_request(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        $this->assertNotNull($submitted->approvalRequest);
        $this->assertDatabaseHas('approval_requests', [
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $submitted->id,
        ]);
    }

    public function test_approval_request_is_linked_to_correct_approver(): void
    {
        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        $this->assertSame(
            $supervisor->id,
            $submitted->approvalRequest->steps->firstWhere('step_order', 1)?->approver_id,
        );
    }

    public function test_submit_rolls_back_if_approval_request_creation_fails(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $draft = $this->draftScenarioRequest($employee);

        $mock = Mockery::mock(LeaveApprovalService::class);
        $mock->shouldReceive('initiateApproval')->once()->andThrow(new RuntimeException('Approval creation failed.'));
        $this->app->instance(LeaveApprovalService::class, $mock);

        $service = app(LeaveRequestService::class);

        try {
            $service->submit($draft);
            $this->fail('Expected approval creation failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Approval creation failed.', $exception->getMessage());
        }

        $this->assertSame(LeaveRequest::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertNull($draft->fresh()->submitted_at);
        $this->assertDatabaseMissing('approval_requests', [
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $draft->id,
        ]);
    }

    public function test_approve_transitions_status_to_approved(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $approved->status);
    }

    public function test_approve_creates_leave_taken_transaction(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->assertDatabaseHas('leave_transactions', [
            'transaction_type' => LeaveTransaction::TYPE_LEAVE_TAKEN,
            'reference_type' => LeaveRequest::class,
            'reference_id' => $approved->id,
        ]);
    }

    public function test_approve_deducts_correct_days_from_balance(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover, $leaveType] = $this->prepareApprovalScenario();
        $entitlementBefore = $this->currentYearEntitlement($employee, $leaveType->code);
        $submitted = $this->submitScenarioRequest($employee, $leaveType);

        $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->assertSame(
            number_format((float) $entitlementBefore->remaining_days - (float) $submitted->requested_days, 2, '.', ''),
            $entitlementBefore->fresh()->remaining_days,
        );
    }

    public function test_approve_sets_source_type_and_source_id_on_transaction(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);
        $transaction = LeaveTransaction::query()
            ->where('reference_type', LeaveRequest::class)
            ->where('reference_id', $approved->id)
            ->where('transaction_type', LeaveTransaction::TYPE_LEAVE_TAKEN)
            ->firstOrFail();

        $this->assertSame(LeaveRequest::class, $transaction->reference_type);
        $this->assertSame($approved->id, $transaction->reference_id);
    }

    public function test_intermediate_approval_does_not_deduct_balance(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover, $leaveType] = $this->prepareApprovalScenario();
        $entitlementBefore = $this->currentYearEntitlement($employee, $leaveType->code);
        $submitted = $this->submitScenarioRequest($employee, $leaveType);

        $this->leaveApprovalService->processApproval(
            $submitted->approvalRequest,
            $supervisor,
            'approved',
            'Supervisor approved.',
        );

        $this->assertSame(LeaveRequest::STATUS_PENDING, $submitted->fresh()->status);
        $this->assertSame($entitlementBefore->remaining_days, $entitlementBefore->fresh()->remaining_days);
        $this->assertDatabaseMissing('leave_transactions', [
            'transaction_type' => LeaveTransaction::TYPE_LEAVE_TAKEN,
            'reference_type' => LeaveRequest::class,
            'reference_id' => $submitted->id,
        ]);
    }

    public function test_double_approval_is_prevented(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->approve($approved, $hrApprover);
    }

    public function test_approve_dispatches_leave_request_approved_event(): void
    {
        Event::fake([LeaveRequestApproved::class]);

        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        Event::assertDispatched(LeaveRequestApproved::class);
    }

    public function test_reject_transitions_status_to_rejected(): void
    {
        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        $this->leaveApprovalService->processApproval(
            $submitted->approvalRequest,
            $supervisor,
            'rejected',
            'Insufficient detail.',
        );

        $this->assertSame(LeaveRequest::STATUS_REJECTED, $submitted->fresh()->status);
    }

    public function test_reject_does_not_create_any_leave_transaction(): void
    {
        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $transactionCount = LeaveTransaction::query()->count();
        $submitted = $this->submitScenarioRequest($employee);

        $this->leaveApprovalService->processApproval(
            $submitted->approvalRequest,
            $supervisor,
            'rejected',
            'Insufficient detail.',
        );

        $this->assertSame($transactionCount, LeaveTransaction::query()->count());
    }

    public function test_reject_does_not_change_balance(): void
    {
        [$employee, $supervisor, , , $leaveType] = $this->prepareApprovalScenario();
        $entitlement = $this->currentYearEntitlement($employee, $leaveType->code);
        $submitted = $this->submitScenarioRequest($employee, $leaveType);

        $this->leaveApprovalService->processApproval(
            $submitted->approvalRequest,
            $supervisor,
            'rejected',
            'Insufficient detail.',
        );

        $this->assertSame($entitlement->remaining_days, $entitlement->fresh()->remaining_days);
    }

    public function test_reject_dispatches_leave_request_rejected_event(): void
    {
        Event::fake([LeaveRequestRejected::class]);

        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        $this->leaveApprovalService->processApproval(
            $submitted->approvalRequest,
            $supervisor,
            'rejected',
            'Insufficient detail.',
        );

        Event::assertDispatched(LeaveRequestRejected::class);
    }

    public function test_admin_can_cancel_approved_request(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $cancelled = $this->leaveRequestService->cancelApproved(
            $approved,
            $this->employee('admin@hrms.local'),
            'Operations request.',
        );

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_cancel_approved_creates_restore_transaction(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->leaveRequestService->cancelApproved(
            $approved,
            $this->employee('admin@hrms.local'),
            'Operations request.',
        );

        $this->assertDatabaseHas('leave_transactions', [
            'transaction_type' => LeaveTransaction::TYPE_RESTORE,
            'reference_type' => LeaveRequest::class,
            'reference_id' => $approved->id,
        ]);
    }

    public function test_cancel_approved_restores_correct_balance(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover, $leaveType] = $this->prepareApprovalScenario();
        $entitlement = $this->currentYearEntitlement($employee, $leaveType->code);
        $balanceBefore = $entitlement->remaining_days;
        $submitted = $this->submitScenarioRequest($employee, $leaveType);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->leaveRequestService->cancelApproved(
            $approved,
            $this->employee('admin@hrms.local'),
            'Operations request.',
        );

        $this->assertSame($balanceBefore, $entitlement->fresh()->remaining_days);
    }

    public function test_employee_cannot_cancel_approved_request(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->cancelApproved($approved, $employee, 'Not allowed.');
    }

    public function test_cancel_approved_without_linked_entitlement_completes_without_restore(): void
    {
        Log::spy();

        [$employee, $supervisor, $departmentHead, $hrApprover, $leaveType] = $this->prepareApprovalScenario('UNPAID', false);
        $submitted = $this->submitScenarioRequest($employee, $leaveType);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $cancelled = $this->leaveRequestService->cancelApproved(
            $approved,
            $this->employee('admin@hrms.local'),
            'Operations request.',
        );

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $cancelled->status);
        $this->assertDatabaseMissing('leave_transactions', [
            'transaction_type' => LeaveTransaction::TYPE_RESTORE,
            'reference_type' => LeaveRequest::class,
            'reference_id' => $approved->id,
        ]);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'Skipping leave balance restore because the approved leave request has no linked entitlement.'))
            ->once();
    }

    public function test_double_deduction_is_prevented(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        try {
            $this->leaveRequestService->approve($approved, $hrApprover);
        } catch (ValidationException) {
            // Expected guard path.
        }

        $this->assertSame(1, LeaveTransaction::query()
            ->where('reference_type', LeaveRequest::class)
            ->where('reference_id', $approved->id)
            ->where('transaction_type', LeaveTransaction::TYPE_LEAVE_TAKEN)
            ->count());
    }

    public function test_restore_without_prior_deduction_is_skipped(): void
    {
        Log::spy();

        [$employee, , , , $leaveType] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee, $leaveType);
        $submitted->forceFill(['status' => LeaveRequest::STATUS_APPROVED])->save();

        $cancelled = $this->leaveRequestService->cancelApproved(
            $submitted->fresh(),
            $this->employee('admin@hrms.local'),
            'No deduction existed.',
        );

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $cancelled->status);
        $this->assertDatabaseMissing('leave_transactions', [
            'transaction_type' => LeaveTransaction::TYPE_RESTORE,
            'reference_type' => LeaveRequest::class,
            'reference_id' => $submitted->id,
        ]);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_submit_regression_behaviour_remains_unchanged(): void
    {
        Storage::fake('public');

        [$employee, , , , $leaveType] = $this->prepareApprovalScenario('ANNUAL', true);
        $draft = $this->draftScenarioRequest($employee, $leaveType);
        $attachment = UploadedFile::fake()->create('medical-note.pdf', 32, 'application/pdf');
        $transactionCount = LeaveTransaction::query()->count();

        $submitted = $this->leaveRequestService->submit($draft, $attachment);

        $this->assertSame(LeaveRequest::STATUS_PENDING, $submitted->status);
        $this->assertNotNull($submitted->approvalRequest);
        $this->assertSame($transactionCount, LeaveTransaction::query()->count());
        Storage::disk('public')->assertExists($submitted->attachment->path);
    }

    public function test_balance_is_company_isolated(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover, $leaveType] = $this->prepareApprovalScenario();
        $otherEmployee = Employee::query()->where('email', 'rio.outsource@example.test')->firstOrFail();
        $otherLeaveType = LeaveType::query()
            ->where('company_id', $otherEmployee->company_id)
            ->where('code', 'ANNUAL')
            ->firstOrFail();
        $otherEntitlement = LeaveEntitlement::query()
            ->where('company_id', $otherEmployee->company_id)
            ->where('employee_id', $otherEmployee->id)
            ->where('leave_type_id', $otherLeaveType->id)
            ->where('year', now('Asia/Jakarta')->year)
            ->firstOrFail();
        $otherRemaining = $otherEntitlement->remaining_days;

        $submitted = $this->submitScenarioRequest($employee, $leaveType);
        $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->assertSame($otherRemaining, $otherEntitlement->fresh()->remaining_days);
    }

    public function test_cancel_pending_does_not_create_any_transaction(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $transactionCount = LeaveTransaction::query()->count();

        $this->leaveRequestService->cancel($submitted, $employee, 'Plans changed.');

        $this->assertSame($transactionCount, LeaveTransaction::query()->count());
    }

    public function test_approve_action_visible_on_pending_request(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $this->advanceToFinalStep($submitted, $supervisor, $departmentHead);

        Livewire::actingAs($hrApprover)
            ->test(AdminViewLeaveRequest::class, ['record' => $submitted->id])
            ->assertActionVisible('approve');
    }

    public function test_approve_action_not_visible_on_approved_request(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        Livewire::actingAs($hrApprover)
            ->test(AdminViewLeaveRequest::class, ['record' => $approved->id])
            ->assertActionHidden('approve');
    }

    public function test_reject_action_visible_on_pending_request(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $this->advanceToFinalStep($submitted, $supervisor, $departmentHead);

        Livewire::actingAs($hrApprover)
            ->test(AdminViewLeaveRequest::class, ['record' => $submitted->id])
            ->assertActionVisible('reject');
    }

    public function test_cancel_approved_action_visible_only_for_admin(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        Livewire::actingAs($this->employee('admin@hrms.local'))
            ->test(AdminViewLeaveRequest::class, ['record' => $approved->id])
            ->assertActionVisible('cancelApproved');
    }

    public function test_cancel_approved_action_not_visible_for_employee_role(): void
    {
        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $approved = $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        $this->assertFalse(Gate::forUser($employee)->allows('cancelApproved', $approved));
    }

    public function test_leave_request_submitted_event_is_dispatched_on_submit(): void
    {
        Event::fake([LeaveRequestSubmitted::class]);

        [$employee] = $this->prepareApprovalScenario();
        $this->submitScenarioRequest($employee);

        Event::assertDispatched(LeaveRequestSubmitted::class);
    }

    public function test_leave_request_approved_event_is_dispatched_on_approve(): void
    {
        Event::fake([LeaveRequestApproved::class]);

        [$employee, $supervisor, $departmentHead, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $this->fullyApproveRequest($submitted, $supervisor, $departmentHead, $hrApprover);

        Event::assertDispatched(LeaveRequestApproved::class);
    }

    public function test_leave_request_rejected_event_is_dispatched_on_reject(): void
    {
        Event::fake([LeaveRequestRejected::class]);

        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        $this->leaveApprovalService->processApproval(
            $submitted->approvalRequest,
            $supervisor,
            'rejected',
            'Insufficient detail.',
        );

        Event::assertDispatched(LeaveRequestRejected::class);
    }

    public function test_leave_request_cancelled_event_is_dispatched_on_cancel(): void
    {
        Event::fake([LeaveRequestCancelled::class]);

        [$employee] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $this->leaveRequestService->cancel($submitted, $employee, 'Plans changed.');

        Event::assertDispatched(LeaveRequestCancelled::class);
    }

    public function test_approval_inbox_shows_leave_request_to_designated_approver(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);

        Livewire::actingAs($supervisor)
            ->test(MyApprovalInbox::class)
            ->assertCanSeeTableRecords($submitted->approvalRequest->steps()->where('approver_id', $supervisor->id)->get());
    }

    public function test_approval_inbox_hides_request_when_approver_moves_outside_scope(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $submitted = $this->submitScenarioRequest($employee);
        $assignedStep = $submitted->approvalRequest->steps()->where('approver_id', $supervisor->id)->firstOrFail();

        $otherGroup = CompanyGroup::query()->create([
            'code' => 'ALT-GROUP',
            'name' => 'Alternate Group',
            'legal_name' => 'Alternate Group',
            'is_active' => true,
        ]);

        $otherCompany = Company::query()->create([
            'company_group_id' => $otherGroup->id,
            'code' => 'ALT-CO',
            'name' => 'Alternate Company',
            'legal_name' => 'Alternate Company',
            'company_type' => 'subsidiary',
            'is_legal_entity' => true,
            'is_active' => true,
        ]);

        Employee::withoutEvents(function () use ($supervisor, $otherGroup, $otherCompany): void {
            $supervisor->forceFill([
                'company_id' => $otherCompany->id,
                'company_group_id' => $otherGroup->id,
                'branch_id' => null,
                'work_location_id' => null,
                'department_id' => null,
                'division_id' => null,
                'position_id' => null,
            ])->saveQuietly();
        });

        Livewire::actingAs($supervisor->fresh())
            ->test(MyApprovalInbox::class)
            ->assertCanNotSeeTableRecords([$assignedStep->fresh()]);
    }

    /**
     * @return array{0: Employee, 1: Employee, 2: Employee, 3: Employee, 4: LeaveType}
     */
    private function prepareApprovalScenario(string $leaveTypeCode = 'ANNUAL', bool $generateEntitlement = true): array
    {
        $supervisor = $this->makeEmployee('employee', ['email' => sprintf('supervisor-%d@example.test', $this->employeeSequence)]);
        $departmentHead = $this->makeEmployee('department_manager', ['email' => sprintf('department-head-%d@example.test', $this->employeeSequence)]);
        $hrApprover = $this->makeEmployee('hr', ['email' => sprintf('hr-approver-%d@example.test', $this->employeeSequence)]);
        $employee = $this->makeEmployee('employee', [
            'email' => sprintf('leave-subject-%d@example.test', $this->employeeSequence),
            'direct_supervisor_id' => $supervisor->id,
        ]);

        $employee->department()->associate($departmentHead->department)->save();
        $employee->department->update(['manager_id' => $departmentHead->id]);

        $leaveType = $this->leaveType($employee->company_id, $leaveTypeCode);

        if ($generateEntitlement && $leaveType->is_paid) {
            $this->leaveEntitlementService->generateEntitlement($employee, $leaveType, now('Asia/Jakarta')->year);
        }

        $this->createLeaveWorkflow($employee->company_id, $employee->company_group_id, $hrApprover);

        return [$employee, $supervisor, $departmentHead, $hrApprover, $leaveType];
    }

    private function submitScenarioRequest(Employee $employee, ?LeaveType $leaveType = null): LeaveRequest
    {
        $draft = $this->draftScenarioRequest($employee, $leaveType);

        return $this->leaveRequestService->submit($draft);
    }

    private function draftScenarioRequest(Employee $employee, ?LeaveType $leaveType = null): LeaveRequest
    {
        $leaveType ??= $this->leaveType($employee->company_id, 'ANNUAL');
        $dates = $this->nextWorkingDatesForEmployee($employee, 1);

        return $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $leaveType->id,
            'start_date' => $dates->first()->toDateString(),
            'end_date' => $dates->first()->toDateString(),
            'reason' => 'Approval workflow test request.',
        ]);
    }

    private function advanceToFinalStep(LeaveRequest $leaveRequest, Employee $supervisor, Employee $departmentHead): LeaveRequest
    {
        $approvalRequest = $leaveRequest->fresh('approvalRequest.steps.approver')->approvalRequest;

        $this->leaveApprovalService->processApproval($approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $approvalRequest = $leaveRequest->fresh('approvalRequest.steps.approver')->approvalRequest;
        $this->leaveApprovalService->processApproval($approvalRequest, $departmentHead, 'approved', 'Department head approved.');

        return $leaveRequest->fresh(['approvalRequest.steps.approver', 'approvalRequest.logs.actor']);
    }

    private function fullyApproveRequest(
        LeaveRequest $leaveRequest,
        Employee $supervisor,
        Employee $departmentHead,
        Employee $hrApprover,
    ): LeaveRequest {
        $this->advanceToFinalStep($leaveRequest, $supervisor, $departmentHead);
        $approvalRequest = $leaveRequest->fresh('approvalRequest.steps.approver')->approvalRequest;

        $this->leaveApprovalService->processApproval($approvalRequest, $hrApprover, 'approved', 'HR approved.');

        return $leaveRequest->fresh(['approvalRequest.logs.actor', 'approvalRequest.steps.approver']);
    }

    private function createLeaveWorkflow(?int $companyId, ?int $companyGroupId, Employee $hrApprover): ApprovalWorkflow
    {
        ApprovalWorkflow::query()
            ->where('company_id', $companyId)
            ->where('company_group_id', $companyGroupId)
            ->where('module_type', 'leave')
            ->delete();

        $workflow = ApprovalWorkflow::query()->create([
            'company_id' => $companyId,
            'company_group_id' => $companyGroupId,
            'code' => 'LEAVE-CUSTOM-'.$this->employeeSequence,
            'name' => 'Leave Custom Workflow',
            'module_type' => 'leave',
            'is_active' => true,
        ]);

        $workflow->steps()->createMany([
            [
                'step_order' => 1,
                'name' => 'Supervisor Review',
                'approver_type' => 'direct_supervisor',
                'is_required' => true,
                'can_reject' => true,
                'can_return' => false,
                'is_final_step' => false,
            ],
            [
                'step_order' => 2,
                'name' => 'Department Head Review',
                'approver_type' => 'department_head',
                'is_required' => true,
                'can_reject' => true,
                'can_return' => false,
                'is_final_step' => false,
            ],
            [
                'step_order' => 3,
                'name' => 'HR Review',
                'approver_type' => 'specific_employee',
                'approver_employee_id' => $hrApprover->id,
                'is_required' => true,
                'can_reject' => true,
                'can_return' => false,
                'is_final_step' => true,
            ],
        ]);

        return $workflow->load('steps');
    }

    private function employee(string $email): Employee
    {
        return Employee::query()->where('email', $email)->firstOrFail();
    }

    private function leaveType(int $companyId, string $code): LeaveType
    {
        return LeaveType::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function currentYearEntitlement(Employee $employee, string $leaveTypeCode): LeaveEntitlement
    {
        $leaveType = $this->leaveType($employee->company_id, $leaveTypeCode);

        return LeaveEntitlement::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', now('Asia/Jakarta')->year)
            ->firstOrFail();
    }

    private function nextWorkingDatesForEmployee(Employee $employee, int $count): Collection
    {
        /** @var WorkdayPattern $pattern */
        $pattern = \App\Models\WorkdayPattern::query()
            ->where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->with('days')
            ->firstOrFail();

        $workingDays = $pattern->days
            ->where('is_working_day', true)
            ->map(function ($day): int {
                $dayOfWeek = (int) $day->day_of_week;

                return $dayOfWeek === 7 ? 0 : $dayOfWeek;
            })
            ->values();

        $holidays = \App\Models\Holiday::query()
            ->where('company_id', $employee->company_id)
            ->whereHas('holidayCalendar', fn ($query) => $query->where('is_active', true))
            ->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $dates = collect();
        $cursor = now('Asia/Jakarta')->copy()->addDays(90 + $this->employeeSequence)->startOfDay();

        while ($dates->count() < $count) {
            if ($workingDays->contains($cursor->dayOfWeek) && ! in_array($cursor->toDateString(), $holidays, true)) {
                $dates->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $dates;
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
            'employee_code' => sprintf('EMP-LA-%03d', $sequence),
            'full_name' => "Leave Approval Employee {$sequence}",
            'first_name' => 'Leave',
            'last_name' => "Approval {$sequence}",
            'email' => sprintf('leave-approval-%03d@example.test', $sequence),
            'phone' => sprintf('08125000%04d', $sequence),
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
            'join_date' => now()->subYears(2)->toDateString(),
            'hire_date' => now()->subYears(2)->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $attributes));

        $employee->syncRoles([$role]);

        return $employee;
    }
}
