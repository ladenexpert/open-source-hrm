<?php

namespace Tests\Feature;

use App\Enums\ApprovalModuleType;
use App\Filament\Employee\Resources\AttendanceCorrections\AttendanceCorrectionResource as PortalAttendanceCorrectionResource;
use App\Filament\Employee\Resources\AttendanceCorrections\Pages\ListAttendanceCorrections as PortalListAttendanceCorrections;
use App\Filament\Employee\Resources\AttendanceCorrections\Pages\ViewAttendanceCorrection as PortalViewAttendanceCorrection;
use App\Filament\Resources\Attendance\AttendanceCorrectionResource as AdminAttendanceCorrectionResource;
use App\Filament\Resources\Attendance\AttendanceCorrectionResource\Pages\ListAttendanceCorrections as AdminListAttendanceCorrections;
use App\Filament\Resources\Attendance\AttendanceCorrectionResource\Pages\ViewAttendanceCorrection as AdminViewAttendanceCorrection;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceLog;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ShiftPattern;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendanceCalculationService;
use App\Services\Attendance\AttendanceCorrectionService;
use App\Services\Attendance\AttendanceLogService;
use App\Services\Attendance\AttendancePolicyResolverService;
use App\Services\Attendance\ShiftResolverService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceCorrectionV143Test extends TestCase
{
    use RefreshDatabase;

    private AttendanceCorrectionService $attendanceCorrectionService;

    private AttendanceCalculationService $attendanceCalculationService;

    private AttendanceLogService $attendanceLogService;

    private AttendancePolicyResolverService $attendancePolicyResolverService;

    private ShiftResolverService $shiftResolverService;

    private int $employeeSequence = 1;

    private int $workLocationSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->attendanceCorrectionService = app(AttendanceCorrectionService::class);
        $this->attendanceCalculationService = app(AttendanceCalculationService::class);
        $this->attendanceLogService = app(AttendanceLogService::class);
        $this->attendancePolicyResolverService = app(AttendancePolicyResolverService::class);
        $this->shiftResolverService = app(ShiftResolverService::class);
    }

    public function test_attendance_corrections_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('attendance_corrections'));

        foreach ([
            'company_id',
            'employee_id',
            'attendance_summary_id',
            'attendance_date',
            'correction_type',
            'reason',
            'requested_clock_in_at',
            'requested_clock_out_at',
            'requested_work_location_id',
            'requested_notes',
            'approved_clock_in_at',
            'approved_clock_out_at',
            'approved_work_location_id',
            'approved_notes',
            'status',
            'submitted_at',
            'submitted_by',
            'approved_at',
            'approved_by',
            'rejected_at',
            'rejected_by',
            'cancelled_at',
            'cancelled_by',
            'approval_request_id',
            'created_by',
            'updated_by',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('attendance_corrections', $column), "Missing expected column [{$column}].");
        }
    }

    public function test_employee_can_create_draft_correction_for_self(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::MONDAY);

        $correction = $this->attendanceCorrectionService->createDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_OUT,
            'reason' => 'Forgot to clock out.',
            'requested_clock_out_at' => $date->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        $this->assertSame($employee->id, $correction->employee_id);
        $this->assertSame(AttendanceCorrection::STATUS_DRAFT, $correction->status);
        $this->assertSame($employee->company_id, $correction->company_id);
    }

    public function test_employee_cannot_create_correction_for_other_employee(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $otherEmployee = $this->newDefaultShiftEmployee($this->employee('maya.contract@example.test'));
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $correction = $this->attendanceCorrectionService->createDraft($employee, [
            'employee_id' => $otherEmployee->id,
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Attempted cross-employee draft.',
        ]);

        $this->assertSame($employee->id, $correction->employee_id);
        $this->assertNotSame($otherEmployee->id, $correction->employee_id);
    }

    public function test_draft_correction_can_be_submitted(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $draft = $this->attendanceCorrectionService->createDraft($employee, [
            'attendance_date' => $this->nextWeekday(Carbon::WEDNESDAY)->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_OUT,
            'reason' => 'Forgot to clock out.',
            'requested_clock_out_at' => $this->nextWeekday(Carbon::WEDNESDAY)->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        $submitted = $this->attendanceCorrectionService->submit($draft, $employee);

        $this->assertSame(AttendanceCorrection::STATUS_PENDING, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertSame($employee->id, $submitted->submitted_by);
    }

    public function test_submit_creates_or_links_approval_request(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $draft = $this->attendanceCorrectionService->createDraft($employee, [
            'attendance_date' => $this->nextWeekday(Carbon::THURSDAY)->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_IN,
            'reason' => 'Forgot to clock in.',
            'requested_clock_in_at' => $this->nextWeekday(Carbon::THURSDAY)->copy()->setTime(8, 0)->toDateTimeString(),
        ]);

        $submitted = $this->attendanceCorrectionService->submit($draft, $employee);

        $this->assertNotNull($submitted->approval_request_id);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $submitted->approval_request_id,
            'approvable_type' => AttendanceCorrection::class,
            'approvable_id' => $submitted->id,
            'module_type' => ApprovalModuleType::ATTENDANCE_CORRECTION->value,
        ]);
    }

    public function test_pending_correction_can_be_approved(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $this->nextWeekday(Carbon::FRIDAY)->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_OUT,
            'reason' => 'Forgot to clock out.',
            'requested_clock_out_at' => $this->nextWeekday(Carbon::FRIDAY)->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $this->assertSame(AttendanceCorrection::STATUS_APPROVED, $submitted->fresh()->status);
        $this->assertNotNull($submitted->fresh()->approved_at);
    }

    public function test_approve_copies_requested_values_when_no_override_provided(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::MONDAY);
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Both times missing.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 5)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 2)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $approved = $submitted->fresh();

        $this->assertSame(
            $approved->requested_clock_in_at?->toDateTimeString(),
            $approved->approved_clock_in_at?->toDateTimeString(),
        );
        $this->assertSame(
            $approved->requested_clock_out_at?->toDateTimeString(),
            $approved->approved_clock_out_at?->toDateTimeString(),
        );
    }

    public function test_submitted_correction_persists_requested_values_for_approval(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::TUESDAY);
        $requestedWorkLocation = $this->createWorkLocationForCompany($employee, 'Requested Location');
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Need both requested times and location preserved.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 12)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 18)->toDateTimeString(),
            'requested_work_location_id' => $requestedWorkLocation->id,
            'requested_notes' => 'Submitted requested attendance correction values.',
        ]);

        $submitted->refresh();

        $this->assertSame('08:12:00', $submitted->requested_clock_in_at?->format('H:i:s'));
        $this->assertSame('17:18:00', $submitted->requested_clock_out_at?->format('H:i:s'));
        $this->assertSame($requestedWorkLocation->id, $submitted->requested_work_location_id);
        $this->assertSame('Submitted requested attendance correction values.', $submitted->requested_notes);
    }

    public function test_admin_approval_action_form_prefills_requested_values(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);
        $requestedWorkLocation = $this->createWorkLocationForCompany($employee, 'Prefill Location');
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Verify admin approval prefill.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 7)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 9)->toDateTimeString(),
            'requested_work_location_id' => $requestedWorkLocation->id,
            'requested_notes' => 'Prefill requested values for HR approval.',
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');

        Livewire::actingAs($hrApprover)
            ->test(AdminViewAttendanceCorrection::class, ['record' => $submitted->id])
            ->mountAction('approve')
            ->assertActionDataSet(function (array $data) use ($submitted, $requestedWorkLocation): void {
                $this->assertDateTimeStateMatches($submitted->requested_clock_in_at, $data['approved_clock_in_at'] ?? null);
                $this->assertDateTimeStateMatches($submitted->requested_clock_out_at, $data['approved_clock_out_at'] ?? null);
                $this->assertSame($requestedWorkLocation->id, (int) ($data['approved_work_location_id'] ?? 0));
                $this->assertSame('Prefill requested values for HR approval.', $data['approved_notes'] ?? null);
            });
    }

    public function test_admin_can_approve_without_reentering_requested_values(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::THURSDAY);
        $requestedWorkLocation = $this->createWorkLocationForCompany($employee, 'Approve Without Reentry');
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Approve using requested values already on the form.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 3)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 11)->toDateTimeString(),
            'requested_work_location_id' => $requestedWorkLocation->id,
            'requested_notes' => 'Use requested values without retyping.',
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');

        Livewire::actingAs($hrApprover)
            ->test(AdminListAttendanceCorrections::class)
            ->mountTableAction('approve', $submitted)
            ->setTableActionData([
                'comments' => 'HR approved without re-entering times.',
            ])
            ->callMountedTableAction();

        $approved = $submitted->fresh();

        $this->assertSame(AttendanceCorrection::STATUS_APPROVED, $approved->status);
        $this->assertSame(
            $approved->requested_clock_in_at?->toDateTimeString(),
            $approved->approved_clock_in_at?->toDateTimeString(),
        );
        $this->assertSame(
            $approved->requested_clock_out_at?->toDateTimeString(),
            $approved->approved_clock_out_at?->toDateTimeString(),
        );
        $this->assertSame($approved->requested_work_location_id, $approved->approved_work_location_id);
        $this->assertSame($approved->requested_notes, $approved->approved_notes);
    }

    public function test_admin_approval_blank_values_fall_back_to_requested_values(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::FRIDAY);
        $requestedWorkLocation = $this->createWorkLocationForCompany($employee, 'Blank Fallback Location');
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Blank values should still use requested correction values.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 14)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 4)->toDateTimeString(),
            'requested_work_location_id' => $requestedWorkLocation->id,
            'requested_notes' => 'Keep these requested values when the form is blank.',
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');

        Livewire::actingAs($hrApprover)
            ->test(AdminListAttendanceCorrections::class)
            ->mountTableAction('approve', $submitted)
            ->setTableActionData([
                'approved_clock_in_at' => null,
                'approved_clock_out_at' => null,
                'approved_work_location_id' => null,
                'approved_notes' => null,
                'comments' => 'HR approved relying on requested values.',
            ])
            ->callMountedTableAction();

        $approved = $submitted->fresh();

        $this->assertSame(AttendanceCorrection::STATUS_APPROVED, $approved->status);
        $this->assertSame(
            $approved->requested_clock_in_at?->toDateTimeString(),
            $approved->approved_clock_in_at?->toDateTimeString(),
        );
        $this->assertSame(
            $approved->requested_clock_out_at?->toDateTimeString(),
            $approved->approved_clock_out_at?->toDateTimeString(),
        );
        $this->assertSame($approved->requested_work_location_id, $approved->approved_work_location_id);
        $this->assertSame($approved->requested_notes, $approved->approved_notes);
    }

    public function test_admin_view_page_can_approve_pending_correction_and_shows_updated_status(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::MONDAY);
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Approve from admin view page.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 1)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 1)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');

        Livewire::actingAs($hrApprover)
            ->test(AdminViewAttendanceCorrection::class, ['record' => $submitted->id])
            ->assertSee('Pending')
            ->mountAction('approve')
            ->setActionData([
                'comments' => 'HR approved from the view page.',
            ])
            ->callMountedAction()
            ->assertSee('Approved');

        $approved = $submitted->fresh();

        $this->assertSame(AttendanceCorrection::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_admin_list_and_portal_view_show_correction_status(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::TUESDAY);
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_OUT,
            'reason' => 'Status visibility regression test.',
            'requested_clock_out_at' => $date->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        Livewire::actingAs($hrApprover)
            ->test(AdminListAttendanceCorrections::class)
            ->assertCanSeeTableRecords([$submitted])
            ->assertTableColumnStateSet('status', AttendanceCorrection::STATUS_PENDING, $submitted);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        Filament::setCurrentPanel('portal');

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceCorrections::class)
            ->assertCanSeeTableRecords([$submitted->fresh()])
            ->assertTableColumnStateSet('status', AttendanceCorrection::STATUS_APPROVED, $submitted->fresh());

        Livewire::actingAs($employee)
            ->test(PortalViewAttendanceCorrection::class, ['record' => $submitted->id])
            ->assertSee('Approved');
    }

    public function test_admin_approve_action_is_hidden_for_non_pending_records(): void
    {
        Filament::setCurrentPanel('admin');

        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_IN,
            'reason' => 'Action visibility after completion.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 0)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        Livewire::actingAs($hrApprover)
            ->test(AdminListAttendanceCorrections::class)
            ->assertTableActionHidden('approve', $submitted->fresh());

        Livewire::actingAs($hrApprover)
            ->test(AdminViewAttendanceCorrection::class, ['record' => $submitted->id])
            ->assertActionHidden('approve');
    }

    public function test_approver_can_modify_approved_values(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::TUESDAY);
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_WRONG_CLOCK_IN,
            'reason' => 'Clocked in using the wrong kiosk time.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 30)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval(
            $submitted->fresh('approvalRequest')->approvalRequest,
            $hrApprover,
            'approved',
            'HR adjusted the approved time.',
            [
                'approved_clock_in_at' => $date->copy()->setTime(8, 10)->toDateTimeString(),
            ],
        );

        $this->assertSame('08:10:00', $submitted->fresh()->approved_clock_in_at?->format('H:i:s'));
    }

    public function test_approved_correction_triggers_summary_recalculation(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);

        $initialSummary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);
        $this->assertSame(AttendanceSummary::STATUS_ABSENT, $initialSummary->status);

        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Forgot both clocks.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $summary = AttendanceSummary::query()->forEmployee($employee)->forDate($date)->firstOrFail();

        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $summary->status);
        $this->assertTrue($summary->is_recalculated);
        $this->assertSame($summary->id, $submitted->fresh()->attendance_summary_id);
    }

    public function test_missing_clock_out_correction_resolves_incomplete_summary(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::THURSDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $summaryBefore = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame(AttendanceSummary::STATUS_INCOMPLETE, $summaryBefore->status);

        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MISSING_CLOCK_OUT,
            'reason' => 'Forgot to clock out.',
            'requested_clock_out_at' => $date->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $summaryAfter = AttendanceSummary::query()->forEmployee($employee)->forDate($date)->firstOrFail();

        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $summaryAfter->status);
        $this->assertTrue($summaryAfter->is_complete);
        $this->assertSame('17:00', $summaryAfter->actual_out_at?->format('H:i'));
    }

    public function test_absent_day_correction_can_create_present_summary(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::FRIDAY);

        $summaryBefore = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);
        $this->assertSame(AttendanceSummary::STATUS_ABSENT, $summaryBefore->status);

        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Forgot both clocks.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $summaryAfter = AttendanceSummary::query()->forEmployee($employee)->forDate($date)->firstOrFail();

        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $summaryAfter->status);
        $this->assertTrue($summaryAfter->is_complete);
    }

    public function test_wrong_clock_in_correction_updates_late_minutes(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $employee = $this->newEmployeeWithPolicy(['late_tolerance_minutes' => 0], $employee);
        $employee->forceFill([
            'direct_supervisor_id' => $supervisor->id,
        ])->save();
        $date = $this->nextWeekday(Carbon::MONDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 30), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summaryBefore = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);
        $this->assertSame(30, $summaryBefore->late_minutes);

        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_WRONG_CLOCK_IN,
            'reason' => 'Device time was wrong.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 5)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $summaryAfter = AttendanceSummary::query()->forEmployee($employee)->forDate($date)->firstOrFail();

        $this->assertSame(AttendanceSummary::STATUS_LATE, $summaryAfter->status);
        $this->assertSame(5, $summaryAfter->late_minutes);
    }

    public function test_rejected_correction_does_not_recalculate_summary(): void
    {
        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $summaryBefore = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Forgot both clocks.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 0)->toDateTimeString(),
            'requested_clock_out_at' => $date->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'rejected', 'Rejected.');

        $summaryAfter = AttendanceSummary::query()->forEmployee($employee)->forDate($date)->firstOrFail();

        $this->assertSame($summaryBefore->status, $summaryAfter->status);
        $this->assertSame($summaryBefore->calculated_at?->toDateTimeString(), $summaryAfter->calculated_at?->toDateTimeString());
    }

    public function test_draft_correction_can_be_cancelled(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $draft = $this->attendanceCorrectionService->createDraft($employee, [
            'attendance_date' => $this->nextWeekday(Carbon::WEDNESDAY)->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Draft cancel test.',
        ]);

        $cancelled = $this->attendanceCorrectionService->cancel($draft, $employee);

        $this->assertSame(AttendanceCorrection::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_pending_correction_can_be_cancelled(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $this->nextWeekday(Carbon::THURSDAY)->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Pending cancel test.',
        ]);

        $cancelled = $this->attendanceCorrectionService->cancel($submitted, $employee);

        $this->assertSame(AttendanceCorrection::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('cancelled', $cancelled->approvalRequest?->fresh()?->status?->value ?? $cancelled->approvalRequest?->fresh()?->status);
    }

    public function test_approved_correction_cannot_be_cancelled(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $this->nextWeekday(Carbon::FRIDAY)->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Approved cancel guard.',
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $this->expectException(ValidationException::class);

        $this->attendanceCorrectionService->cancel($submitted->fresh(), $employee);
    }

    public function test_rejected_correction_cannot_be_cancelled(): void
    {
        [$employee, $supervisor] = $this->prepareApprovalScenario();
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $this->nextWeekday(Carbon::MONDAY)->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Rejected cancel guard.',
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'rejected', 'Rejected.');

        $this->expectException(ValidationException::class);

        $this->attendanceCorrectionService->cancel($submitted->fresh(), $employee);
    }

    public function test_raw_attendance_logs_are_not_modified_by_correction(): void
    {
        [$employee, $supervisor, $hrApprover] = $this->prepareApprovalScenario();
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $clockIn = $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 30), AttendanceLog::EVENT_CLOCK_OUT);
        $beforePayload = AttendanceLog::query()->orderBy('id')->get()->map(fn (AttendanceLog $log): array => [
            'id' => $log->id,
            'clocked_at' => $log->clocked_at?->toDateTimeString(),
            'event_type' => $log->event_type,
        ])->all();

        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_WRONG_CLOCK_IN,
            'reason' => 'Wrong clock in time.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 10)->toDateTimeString(),
        ]);

        $this->attendanceCorrectionService->processApproval($submitted->approvalRequest, $supervisor, 'approved', 'Supervisor approved.');
        $this->attendanceCorrectionService->processApproval($submitted->fresh('approvalRequest')->approvalRequest, $hrApprover, 'approved', 'HR approved.');

        $afterPayload = AttendanceLog::query()->orderBy('id')->get()->map(fn (AttendanceLog $log): array => [
            'id' => $log->id,
            'clocked_at' => $log->clocked_at?->toDateTimeString(),
            'event_type' => $log->event_type,
        ])->all();

        $this->assertSame($beforePayload, $afterPayload);
        $this->assertTrue(AttendanceLog::query()->whereKey($clockIn->id)->exists());
    }

    public function test_approved_correction_overlay_is_used_by_calculation_service(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::WEDNESDAY);

        AttendanceCorrection::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_summary_id' => null,
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Approved overlay.',
            'requested_clock_in_at' => $date->copy()->setTime(8, 0),
            'requested_clock_out_at' => $date->copy()->setTime(17, 0),
            'approved_clock_in_at' => $date->copy()->setTime(8, 0),
            'approved_clock_out_at' => $date->copy()->setTime(17, 0),
            'status' => AttendanceCorrection::STATUS_APPROVED,
            'approved_at' => now(config('app.timezone')),
            'approved_by' => $employee->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame('08:00', $summary->actual_in_at?->format('H:i'));
        $this->assertSame('17:00', $summary->actual_out_at?->format('H:i'));
        $this->assertStringContainsString('overlay mode', $summary->calculation_notes ?? '');
    }

    public function test_latest_approved_correction_wins_when_multiple_exist(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::THURSDAY);

        AttendanceCorrection::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Older correction.',
            'approved_clock_in_at' => $date->copy()->setTime(8, 30),
            'approved_clock_out_at' => $date->copy()->setTime(17, 0),
            'status' => AttendanceCorrection::STATUS_APPROVED,
            'approved_at' => now(config('app.timezone'))->subHour(),
            'approved_by' => $employee->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        AttendanceCorrection::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Latest correction.',
            'approved_clock_in_at' => $date->copy()->setTime(8, 5),
            'approved_clock_out_at' => $date->copy()->setTime(17, 10),
            'status' => AttendanceCorrection::STATUS_APPROVED,
            'approved_at' => now(config('app.timezone')),
            'approved_by' => $employee->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertSame('08:05', $summary->actual_in_at?->format('H:i'));
        $this->assertSame('17:10', $summary->actual_out_at?->format('H:i'));
    }

    public function test_attendance_correction_is_company_scoped(): void
    {
        $companyAEmployee = $this->newDefaultShiftEmployee($this->employee('andi.permanent@example.test'));
        $companyBEmployee = $this->newDefaultShiftEmployee($this->employee('rio.outsource@example.test'));
        $date = $this->nextWeekday(Carbon::FRIDAY);

        $companyBCorrection = $this->attendanceCorrectionService->createDraft($companyBEmployee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Cross-company scope test.',
        ]);

        $this->assertFalse(
            AttendanceCorrection::query()
                ->forCompany($companyAEmployee->company_id)
                ->whereKey($companyBCorrection->id)
                ->exists()
        );
    }

    public function test_company_admin_cannot_view_other_company_corrections(): void
    {
        Filament::setCurrentPanel('admin');

        $companyAEmployee = $this->newDefaultShiftEmployee($this->employee('andi.permanent@example.test'));
        $companyBEmployee = $this->newDefaultShiftEmployee($this->employee('rio.outsource@example.test'));
        $companyAdmin = $this->makeCompanyAdmin($companyAEmployee);
        $date = $this->nextWeekday(Carbon::MONDAY);

        $companyACorrection = $this->attendanceCorrectionService->createDraft($companyAEmployee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Company A correction.',
        ]);
        $companyBCorrection = $this->attendanceCorrectionService->createDraft($companyBEmployee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Company B correction.',
        ]);

        Livewire::actingAs($companyAdmin)
            ->test(AdminListAttendanceCorrections::class)
            ->assertCanSeeTableRecords([$companyACorrection])
            ->assertCanNotSeeTableRecords([$companyBCorrection]);
    }

    public function test_employee_cannot_access_admin_correction_resource(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $this->actingAs($employee)
            ->get(AdminAttendanceCorrectionResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertForbidden();
    }

    public function test_admin_can_view_attendance_correction_resource(): void
    {
        $this->actingAs($this->employee('admin@hrms.local'))
            ->get(AdminAttendanceCorrectionResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertOk();
    }

    public function test_employee_can_access_own_portal_correction_resource(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $this->actingAs($employee)
            ->get(PortalAttendanceCorrectionResource::getUrl(isAbsolute: false, panel: 'portal'))
            ->assertOk();
    }

    public function test_employee_portal_correction_resource_is_self_scoped(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->newDefaultShiftEmployee($this->employee('andi.permanent@example.test'));
        $otherEmployee = $this->newDefaultShiftEmployee($this->employee('maya.contract@example.test'));
        $date = $this->nextWeekday(Carbon::TUESDAY);

        $ownCorrection = $this->attendanceCorrectionService->createDraft($employee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Own correction.',
        ]);
        $otherCorrection = $this->attendanceCorrectionService->createDraft($otherEmployee, [
            'attendance_date' => $date->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Other correction.',
        ]);

        Livewire::actingAs($employee)
            ->test(PortalListAttendanceCorrections::class)
            ->assertCanSeeTableRecords([$ownCorrection])
            ->assertCanNotSeeTableRecords([$otherCorrection]);
    }

    public function test_policy_prevents_employee_approval(): void
    {
        [$employee] = $this->prepareApprovalScenario();
        $submitted = $this->submitCorrectionDraft($employee, [
            'attendance_date' => $this->nextWeekday(Carbon::WEDNESDAY)->toDateString(),
            'correction_type' => AttendanceCorrection::TYPE_MANUAL_ADJUSTMENT,
            'reason' => 'Approval guard test.',
        ]);

        $this->assertFalse(Gate::forUser($employee)->allows('approve', $submitted));
    }

    public function test_existing_attendance_calculation_tests_still_pass(): void
    {
        $employee = $this->newDefaultShiftEmployee();
        $date = $this->nextWeekday(Carbon::THURSDAY);

        $this->createAttendanceLog($employee, $date->copy()->setTime(8, 0), AttendanceLog::EVENT_CLOCK_IN);
        $this->createAttendanceLog($employee, $date->copy()->setTime(17, 0), AttendanceLog::EVENT_CLOCK_OUT);

        $summary = $this->attendanceCalculationService->calculateForEmployeeDate($employee, $date);

        $this->assertInstanceOf(AttendanceSummary::class, $summary);
        $this->assertSame(AttendanceSummary::STATUS_PRESENT, $summary->status);
    }

    public function test_existing_attendance_log_tests_still_pass(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $log = $this->attendanceLogService->clockIn($employee);

        $this->assertInstanceOf(AttendanceLog::class, $log);
    }

    public function test_existing_attendance_foundation_tests_still_pass(): void
    {
        $employee = $this->employee('andi.permanent@example.test');

        $this->assertInstanceOf(
            ShiftPattern::class,
            $this->shiftResolverService->resolveShift($employee, now(config('app.timezone')))
        );

        $this->assertInstanceOf(
            AttendancePolicy::class,
            $this->attendancePolicyResolverService->resolvePolicy($employee)
        );
    }

    /**
     * @return array{0: Employee, 1: Employee, 2: Employee}
     */
    private function prepareApprovalScenario(): array
    {
        $employee = $this->newDefaultShiftEmployee();
        $supervisor = $this->makeEmployeeFrom($employee, [
            'email' => sprintf('attendance-correction-supervisor-%03d@example.test', $this->employeeSequence),
            'employee_code' => sprintf('EMP-AC-SPV-%03d', $this->employeeSequence),
            'full_name' => 'Attendance Correction Supervisor',
            'first_name' => 'Attendance',
            'last_name' => 'Supervisor',
        ]);
        $hrApprover = $this->makeEmployeeFrom($employee, [
            'email' => sprintf('attendance-correction-hr-%03d@example.test', $this->employeeSequence),
            'employee_code' => sprintf('EMP-AC-HR-%03d', $this->employeeSequence),
            'full_name' => 'Attendance Correction HR',
            'first_name' => 'Attendance',
            'last_name' => 'HR',
        ]);

        $hrApprover->syncRoles(['hr']);

        $employee->forceFill([
            'direct_supervisor_id' => $supervisor->id,
        ])->save();

        $this->createAttendanceCorrectionWorkflow($employee, $hrApprover);

        return [$employee->fresh(), $supervisor->fresh(), $hrApprover->fresh()];
    }

    private function submitCorrectionDraft(Employee $employee, array $payload): AttendanceCorrection
    {
        $draft = $this->attendanceCorrectionService->createDraft($employee, $payload);

        return $this->attendanceCorrectionService->submit($draft, $employee);
    }

    private function createAttendanceCorrectionWorkflow(Employee $employee, Employee $hrApprover): ApprovalWorkflow
    {
        ApprovalWorkflow::query()
            ->where('company_id', $employee->company_id)
            ->where('module_type', ApprovalModuleType::ATTENDANCE_CORRECTION->value)
            ->delete();

        $workflow = ApprovalWorkflow::query()->create([
            'company_id' => $employee->company_id,
            'company_group_id' => $employee->company_group_id,
            'code' => 'ATTENDANCE-CORRECTION-'.$this->employeeSequence,
            'name' => 'Attendance Correction Workflow',
            'module_type' => ApprovalModuleType::ATTENDANCE_CORRECTION->value,
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

    private function newDefaultShiftEmployee(?Employee $template = null): Employee
    {
        $template ??= $this->employee('andi.permanent@example.test');

        return $this->makeEmployeeFrom($template, [
            'attendance_policy_id' => null,
            'attendance_location_mode_override' => null,
            'branch_id' => null,
            'department_id' => null,
            'division_id' => null,
            'position_id' => null,
            'direct_supervisor_id' => null,
        ]);
    }

    private function newEmployeeWithPolicy(array $policyOverrides = [], ?Employee $template = null): Employee
    {
        $template ??= $this->employee('andi.permanent@example.test');
        $employee = $this->newDefaultShiftEmployee($template);
        $policy = $this->createAttendancePolicy($template, $policyOverrides);

        $employee->forceFill([
            'attendance_policy_id' => $policy->id,
        ])->save();

        return $employee->fresh();
    }

    private function createAttendancePolicy(Employee $template, array $overrides = []): AttendancePolicy
    {
        return AttendancePolicy::query()->create(array_merge([
            'company_id' => $template->company_id,
            'code' => sprintf('ACP-%03d', $this->employeeSequence),
            'name' => 'Attendance Correction Test Policy',
            'location_mode' => AttendancePolicy::LOCATION_MODE_FIXED,
            'gps_required' => false,
            'selfie_required' => false,
            'radius_validation_enabled' => false,
            'radius_meters' => null,
            'late_tolerance_minutes' => 10,
            'early_out_tolerance_minutes' => 10,
            'minimum_work_minutes' => 0,
            'auto_absent_after_minutes' => 120,
            'overtime_threshold_minutes' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function createAttendanceLog(
        Employee $employee,
        Carbon $clockedAt,
        string $eventType,
        bool $isValid = true,
        ?string $validationMessage = null,
    ): AttendanceLog {
        return AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $clockedAt->toDateString(),
            'event_type' => $eventType,
            'clocked_at' => $clockedAt,
            'source' => AttendanceLog::SOURCE_WEB,
            'is_valid' => $isValid,
            'validation_message' => $validationMessage,
            'created_by' => $employee->id,
        ]);
    }

    private function createWorkLocationForCompany(Employee $template, string $name): WorkLocation
    {
        $sequence = $this->workLocationSequence++;

        return WorkLocation::query()->create([
            'company_id' => $template->company_id,
            'branch_id' => null,
            'code' => sprintf('AC-WL-%03d', $sequence),
            'name' => "{$name} {$sequence}",
            'address' => 'Attendance correction test work location',
            'is_active' => true,
        ]);
    }

    private function makeEmployeeFrom(Employee $template, array $overrides = []): Employee
    {
        $sequence = $this->employeeSequence++;

        return Employee::query()->create(array_merge([
            'employee_code' => sprintf('EMP-AC-%03d', $sequence),
            'full_name' => "Attendance Correction User {$sequence}",
            'first_name' => 'Attendance',
            'last_name' => "Correction {$sequence}",
            'email' => sprintf('attendance-correction-%03d@example.test', $sequence),
            'company_id' => $template->company_id,
            'company_group_id' => $template->company_group_id,
            'branch_id' => $template->branch_id,
            'work_location_id' => $template->work_location_id,
            'department_id' => $template->department_id,
            'division_id' => $template->division_id,
            'position_id' => $template->position_id,
            'job_level_id' => $template->job_level_id,
            'job_grade_id' => $template->job_grade_id,
            'employment_status_id' => $template->employment_status_id,
            'employment_type_id' => $template->employment_type_id,
            'contract_type_id' => $template->contract_type_id,
            'identity_type_id' => $template->identity_type_id,
            'religion_id' => $template->religion_id,
            'marital_status_id' => $template->marital_status_id,
            'employment_type' => $template->employment_type,
            'hire_date' => now(config('app.timezone'))->toDateString(),
            'join_date' => now(config('app.timezone'))->toDateString(),
            'is_active' => true,
            'password' => 'password123',
        ], $overrides));
    }

    private function makeCompanyAdmin(Employee $template): Employee
    {
        $employee = $this->makeEmployeeFrom($template, [
            'email' => sprintf('attendance-correction-admin-%03d@example.test', $this->employeeSequence),
            'employee_code' => sprintf('EMP-AC-ADM-%03d', $this->employeeSequence),
            'full_name' => 'Attendance Correction Company Admin',
            'first_name' => 'Attendance',
            'last_name' => 'Admin',
        ]);

        $employee->assignRole('company_admin');

        return $employee;
    }

    private function nextWeekday(int $dayConstant): Carbon
    {
        return now('Asia/Jakarta')->next($dayConstant)->startOfDay();
    }

    private function assertDateTimeStateMatches(?Carbon $expected, mixed $actual): void
    {
        if (! $expected instanceof Carbon) {
            $this->assertNull($actual);

            return;
        }

        $this->assertNotNull($actual);
        $this->assertSame(
            $expected->toDateTimeString(),
            Carbon::parse($actual, config('app.timezone'))->toDateTimeString(),
        );
    }
}
