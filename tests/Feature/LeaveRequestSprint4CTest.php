<?php

namespace Tests\Feature;

use App\Filament\Employee\Resources\LeaveRequests\LeaveRequestResource as PortalLeaveRequestResource;
use App\Filament\Employee\Resources\LeaveRequests\Pages\ListLeaveRequests as PortalListLeaveRequests;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource as AdminLeaveRequestResource;
use App\Filament\Resources\LeaveRequests\Pages\ViewLeaveRequest as AdminViewLeaveRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\WorkdayPattern;
use App\Services\LeaveCalculationService;
use App\Services\LeaveRequestService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveRequestSprint4CTest extends TestCase
{
    use RefreshDatabase;

    private LeaveCalculationService $leaveCalculationService;

    private LeaveRequestService $leaveRequestService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->leaveCalculationService = app(LeaveCalculationService::class);
        $this->leaveRequestService = app(LeaveRequestService::class);
    }

    public function test_single_working_day_returns_1(): void
    {
        [$monday] = $this->mondayThroughFridayWindow();

        $days = $this->leaveCalculationService->calculateDays(
            $monday,
            $monday,
            collect([1, 2, 3, 4, 5]),
            collect(),
        );

        $this->assertSame(1.0, $days);
    }

    public function test_full_week_returns_5(): void
    {
        [$monday, $friday] = $this->mondayThroughFridayWindow();

        $days = $this->leaveCalculationService->calculateDays(
            $monday,
            $friday,
            collect([1, 2, 3, 4, 5]),
            collect(),
        );

        $this->assertSame(5.0, $days);
    }

    public function test_weekend_days_are_excluded(): void
    {
        [$monday] = $this->mondayThroughFridayWindow();
        $sunday = $monday->copy()->addDays(6);

        $days = $this->leaveCalculationService->calculateDays(
            $monday,
            $sunday,
            collect([1, 2, 3, 4, 5]),
            collect(),
        );

        $this->assertSame(5.0, $days);
    }

    public function test_holiday_on_working_day_is_excluded(): void
    {
        [$monday] = $this->mondayThroughFridayWindow();
        $friday = $monday->copy()->addDays(4);
        $wednesday = $monday->copy()->addDays(2);

        $days = $this->leaveCalculationService->calculateDays(
            $monday,
            $friday,
            collect([1, 2, 3, 4, 5]),
            collect([$wednesday]),
        );

        $this->assertSame(4.0, $days);
    }

    public function test_holiday_on_weekend_does_not_double_exclude(): void
    {
        [$monday] = $this->mondayThroughFridayWindow();
        $sunday = $monday->copy()->addDays(6);
        $saturday = $monday->copy()->addDays(5);

        $days = $this->leaveCalculationService->calculateDays(
            $monday,
            $sunday,
            collect([1, 2, 3, 4, 5]),
            collect([$saturday]),
        );

        $this->assertSame(5.0, $days);
    }

    public function test_half_day_always_returns_0_5(): void
    {
        [$monday] = $this->mondayThroughFridayWindow();

        $days = $this->leaveCalculationService->calculateDays(
            $monday,
            $monday,
            collect([1, 2, 3, 4, 5]),
            collect(),
            true,
            LeaveRequest::HALF_DAY_FIRST,
        );

        $this->assertSame(0.5, $days);
    }

    public function test_all_holidays_returns_0(): void
    {
        [$monday, $friday] = $this->mondayThroughFridayWindow();

        $days = $this->leaveCalculationService->calculateDays(
            $monday,
            $friday,
            collect([1, 2, 3, 4, 5]),
            collect([
                $monday,
                $monday->copy()->addDay(),
                $monday->copy()->addDays(2),
                $monday->copy()->addDays(3),
                $friday,
            ]),
        );

        $this->assertSame(0.0, $days);
    }

    public function test_multi_week_calculation_is_correct(): void
    {
        [$monday] = $this->mondayThroughFridayWindow();
        $secondFriday = $monday->copy()->addDays(11);
        $holiday = $monday->copy()->addDays(7);

        $days = $this->leaveCalculationService->calculateDays(
            $monday,
            $secondFriday,
            collect([1, 2, 3, 4, 5]),
            collect([$holiday]),
        );

        $this->assertSame(9.0, $days);
    }

    public function test_create_draft_creates_request_with_draft_status(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $leaveType = $this->leaveType($employee->company_id, 'ANNUAL');
        [$date] = $this->nextWorkingDatesForEmployee($employee, 1);

        $leaveRequest = $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $leaveType->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'reason' => 'Draft request.',
        ]);

        $this->assertSame(LeaveRequest::STATUS_DRAFT, $leaveRequest->status);
    }

    public function test_submit_transitions_status_to_pending(): void
    {
        $draft = $this->draftRequestFor('andi.permanent@example.test', 'ANNUAL');

        $submitted = $this->leaveRequestService->submit($draft);

        $this->assertSame(LeaveRequest::STATUS_PENDING, $submitted->status);
    }

    public function test_submit_sets_submitted_at_timestamp(): void
    {
        $draft = $this->draftRequestFor('andi.permanent@example.test', 'ANNUAL');

        $submitted = $this->leaveRequestService->submit($draft);

        $this->assertNotNull($submitted->submitted_at);
    }

    public function test_submit_blocked_when_paid_leave_balance_insufficient(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $entitlement = $this->currentYearEntitlement($employee, 'ANNUAL');
        $entitlement->update([
            'used_days' => 9,
            'remaining_days' => 3,
        ]);

        [$startDate] = $this->nextWorkingDatesForEmployee($employee, 1);
        $endDate = $this->nextWorkingDatesForEmployee($employee, 5, $startDate->copy()->subDay())->last();

        $draft = $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $this->leaveType($employee->company_id, 'ANNUAL')->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->submit($draft);
    }

    public function test_submit_allowed_when_unpaid_leave_regardless_of_balance(): void
    {
        $draft = $this->draftRequestFor('andi.permanent@example.test', 'UNPAID', 5);

        $submitted = $this->leaveRequestService->submit($draft);

        $this->assertSame(LeaveRequest::STATUS_PENDING, $submitted->status);
    }

    public function test_submit_blocked_when_overlap_with_pending_request(): void
    {
        $existing = $this->pendingRequestFor('andi.permanent@example.test', 'ANNUAL');

        $draft = $this->draftRequestUsingDates(
            $this->employee('andi.permanent@example.test'),
            $this->leaveType($existing->company_id, 'ANNUAL'),
            $existing->start_date->copy(),
            $existing->end_date->copy(),
        );

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->submit($draft);
    }

    public function test_submit_blocked_when_overlap_with_approved_request(): void
    {
        $approved = $this->approvedRequestFor('andi.permanent@example.test', 'ANNUAL');

        $draft = $this->draftRequestUsingDates(
            $this->employee('andi.permanent@example.test'),
            $this->leaveType($approved->company_id, 'ANNUAL'),
            $approved->start_date->copy(),
            $approved->end_date->copy(),
        );

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->submit($draft);
    }

    public function test_submit_not_blocked_by_draft_overlap(): void
    {
        $existing = $this->draftRequestFor('andi.permanent@example.test', 'ANNUAL');

        $draft = $this->draftRequestUsingDates(
            $this->employee('andi.permanent@example.test'),
            $this->leaveType($existing->company_id, 'ANNUAL'),
            $existing->start_date->copy(),
            $existing->end_date->copy(),
        );

        $submitted = $this->leaveRequestService->submit($draft);

        $this->assertSame(LeaveRequest::STATUS_PENDING, $submitted->status);
    }

    public function test_submit_not_blocked_by_cancelled_overlap(): void
    {
        $existing = $this->pendingRequestFor('andi.permanent@example.test', 'ANNUAL');
        $cancelled = $this->leaveRequestService->cancel($existing, $this->employee('andi.permanent@example.test'));

        $draft = $this->draftRequestUsingDates(
            $this->employee('andi.permanent@example.test'),
            $this->leaveType($cancelled->company_id, 'ANNUAL'),
            $cancelled->start_date->copy(),
            $cancelled->end_date->copy(),
        );

        $submitted = $this->leaveRequestService->submit($draft);

        $this->assertSame(LeaveRequest::STATUS_PENDING, $submitted->status);
    }

    public function test_submit_blocked_when_attachment_required_but_not_provided(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $leaveType = $this->leaveType($employee->company_id, 'SICK');
        [$date] = $this->nextWorkingDatesForEmployee($employee, 1);

        $draft = $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $leaveType->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->submit($draft);
    }

    public function test_submit_allowed_when_attachment_optional_and_not_provided(): void
    {
        $draft = $this->draftRequestFor('andi.permanent@example.test', 'ANNUAL');

        $submitted = $this->leaveRequestService->submit($draft);

        $this->assertSame(LeaveRequest::STATUS_PENDING, $submitted->status);
    }

    public function test_submit_saves_attachment_when_provided(): void
    {
        Storage::fake('public');

        $draft = $this->draftRequestFor('andi.permanent@example.test', 'ANNUAL');
        $attachment = UploadedFile::fake()->create('support.pdf', 64, 'application/pdf');

        $submitted = $this->leaveRequestService->submit($draft, $attachment);

        $this->assertDatabaseHas('leave_request_attachments', [
            'leave_request_id' => $submitted->id,
            'original_filename' => 'support.pdf',
        ]);

        Storage::disk('public')->assertExists($submitted->attachment->path);
    }

    public function test_submit_resolves_and_stores_leave_entitlement_id(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $draft = $this->draftRequestFor($employee->email, 'ANNUAL');
        $expectedEntitlement = $this->currentYearEntitlement($employee, 'ANNUAL');

        $submitted = $this->leaveRequestService->submit($draft);

        $this->assertSame($expectedEntitlement->id, $submitted->leave_entitlement_id);
    }

    public function test_submit_stores_null_entitlement_id_when_no_active_entitlement(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $leaveType = LeaveType::query()->create([
            'company_id' => $employee->company_id,
            'code' => 'UNPAID-NO-ENT',
            'name' => 'Unpaid No Entitlement',
            'description' => 'Temporary test leave type.',
            'is_paid' => false,
            'requires_attachment' => false,
            'allow_half_day' => true,
            'allow_carry_forward' => false,
            'is_active' => true,
        ]);

        [$date] = $this->nextWorkingDatesForEmployee($employee, 1);
        $draft = $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $leaveType->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
        ]);

        $submitted = $this->leaveRequestService->submit($draft);

        $this->assertNull($submitted->leave_entitlement_id);
    }

    public function test_cancel_from_draft_status_succeeds(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $draft = $this->draftRequestFor($employee->email, 'ANNUAL');

        $cancelled = $this->leaveRequestService->cancel($draft, $employee);

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_cancel_from_pending_status_succeeds(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $pending = $this->pendingRequestFor($employee->email, 'ANNUAL');

        $cancelled = $this->leaveRequestService->cancel($pending, $employee);

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_cancel_from_approved_status_throws(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $approved = $this->approvedRequestFor($employee->email, 'ANNUAL');

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->cancel($approved, $employee);
    }

    public function test_cancel_from_rejected_status_throws(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $rejected = $this->draftRequestFor($employee->email, 'ANNUAL');
        $rejected->forceFill([
            'status' => LeaveRequest::STATUS_REJECTED,
            'submitted_at' => now(),
        ])->save();

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->cancel($rejected, $employee);
    }

    public function test_cancel_sets_cancelled_at_and_cancelled_by(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $pending = $this->pendingRequestFor($employee->email, 'ANNUAL');

        $cancelled = $this->leaveRequestService->cancel($pending, $employee, 'Change of plans.');

        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame($employee->id, $cancelled->cancelled_by);
        $this->assertSame('Change of plans.', $cancelled->cancellation_reason);
    }

    public function test_zero_working_days_request_is_rejected(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $holiday = Holiday::query()
            ->where('company_id', $employee->company_id)
            ->orderBy('date')
            ->firstOrFail();

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $this->leaveType($employee->company_id, 'ANNUAL')->id,
            'start_date' => $holiday->date->toDateString(),
            'end_date' => $holiday->date->toDateString(),
        ]);
    }

    public function test_half_day_not_allowed_when_leave_type_disallows_it(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        [$date] = $this->nextWorkingDatesForEmployee($employee, 1);

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $this->leaveType($employee->company_id, 'SICK')->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'is_half_day' => true,
            'half_day_type' => LeaveRequest::HALF_DAY_FIRST,
        ]);
    }

    public function test_half_day_requires_same_start_and_end_date(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        [$startDate, $endDate] = $this->nextWorkingDatesForEmployee($employee, 2);

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $this->leaveType($employee->company_id, 'ANNUAL')->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_half_day' => true,
            'half_day_type' => LeaveRequest::HALF_DAY_SECOND,
        ]);
    }

    public function test_request_is_scoped_to_company(): void
    {
        $companyAEmployee = $this->employee('andi.permanent@example.test');
        $companyBRequest = $this->draftRequestFor('rio.outsource@example.test', 'ANNUAL');

        $this->assertFalse(
            LeaveRequest::query()->forEmployee($companyAEmployee)->whereKey($companyBRequest->id)->exists()
        );
        $this->assertFalse(
            Gate::forUser($companyAEmployee)->allows('view', $companyBRequest)
        );

        $this->expectException(ValidationException::class);

        $this->leaveRequestService->cancel($companyBRequest, $companyAEmployee);
    }

    public function test_no_balance_mutation_occurs_on_submit(): void
    {
        $employee = $this->employee('andi.permanent@example.test');
        $entitlement = $this->currentYearEntitlement($employee, 'ANNUAL');
        $originalUsedDays = $entitlement->used_days;
        $transactionCount = LeaveTransaction::query()->count();
        $draft = $this->draftRequestFor($employee->email, 'ANNUAL');

        $this->leaveRequestService->submit($draft);

        $this->assertSame($originalUsedDays, $entitlement->fresh()->used_days);
        $this->assertSame($transactionCount, LeaveTransaction::query()->count());
    }

    public function test_leave_request_resource_accessible_by_authorized_admin(): void
    {
        $this->actingAs($this->employee('admin@hrms.local'))
            ->get(AdminLeaveRequestResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertOk();
    }

    public function test_leave_request_resource_not_accessible_by_unauthorized_user(): void
    {
        $this->actingAs($this->employee('andi.permanent@example.test'))
            ->get(AdminLeaveRequestResource::getUrl(isAbsolute: false, panel: 'admin'))
            ->assertForbidden();
    }

    public function test_admin_resource_does_not_expose_approve_action(): void
    {
        Filament::setCurrentPanel('admin');

        $leaveRequest = $this->pendingRequestFor('andi.permanent@example.test', 'ANNUAL');

        Livewire::actingAs($this->employee('admin@hrms.local'))
            ->test(AdminViewLeaveRequest::class, ['record' => $leaveRequest->id])
            ->assertActionDoesNotExist('approve')
            ->assertActionDoesNotExist('reject');
    }

    public function test_employee_can_view_own_leave_request_list(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->employee('andi.permanent@example.test');
        $ownRequest = $this->draftRequestFor($employee->email, 'ANNUAL');

        Livewire::actingAs($employee)
            ->test(PortalListLeaveRequests::class)
            ->assertCanSeeTableRecords([$ownRequest]);
    }

    public function test_employee_cannot_view_another_employees_requests(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->employee('andi.permanent@example.test');
        $otherRequest = $this->draftRequestFor('maya.contract@example.test', 'ANNUAL');

        Livewire::actingAs($employee)
            ->test(PortalListLeaveRequests::class)
            ->assertCanNotSeeTableRecords([$otherRequest]);

        $this->actingAs($employee)
            ->get(PortalLeaveRequestResource::getUrl('view', ['record' => $otherRequest], isAbsolute: false, panel: 'portal'))
            ->assertNotFound();
    }

    public function test_employee_can_submit_leave_request_via_portal(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->employee('andi.permanent@example.test');
        $draft = $this->draftRequestFor($employee->email, 'ANNUAL');

        Livewire::actingAs($employee)
            ->test(PortalListLeaveRequests::class)
            ->callTableAction('submit', $draft)
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $this->assertSame(LeaveRequest::STATUS_PENDING, $draft->fresh()->status);
    }

    public function test_employee_can_cancel_own_pending_request(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->employee('andi.permanent@example.test');
        $pending = $this->pendingRequestFor($employee->email, 'ANNUAL');

        Livewire::actingAs($employee)
            ->test(PortalListLeaveRequests::class)
            ->callTableAction('cancel', $pending, data: [
                'cancellation_reason' => 'Personal schedule change.',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $pending->fresh()->status);
    }

    public function test_employee_cannot_cancel_approved_request(): void
    {
        Filament::setCurrentPanel('portal');

        $employee = $this->employee('andi.permanent@example.test');
        $approved = $this->approvedRequestFor($employee->email, 'ANNUAL');

        Livewire::actingAs($employee)
            ->test(PortalListLeaveRequests::class)
            ->assertTableActionHidden('cancel', $approved);
    }

    private function mondayThroughFridayWindow(): array
    {
        $monday = now('Asia/Jakarta')->next(Carbon::MONDAY)->startOfDay();

        return [$monday, $monday->copy()->addDays(4)];
    }

    private function company(string $code): Company
    {
        return Company::query()->where('code', $code)->firstOrFail();
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

    private function draftRequestFor(string $email, string $leaveTypeCode, int $workingDayCount = 1): LeaveRequest
    {
        $employee = $this->employee($email);
        $leaveType = $this->leaveType($employee->company_id, $leaveTypeCode);
        $dates = $this->nextWorkingDatesForEmployee($employee, $workingDayCount);

        return $this->draftRequestUsingDates(
            $employee,
            $leaveType,
            $dates->first(),
            $dates->last(),
        );
    }

    private function draftRequestUsingDates(Employee $employee, LeaveType $leaveType, Carbon $startDate, Carbon $endDate): LeaveRequest
    {
        return $this->leaveRequestService->createDraft($employee, [
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'reason' => 'Test leave request.',
        ]);
    }

    private function pendingRequestFor(string $email, string $leaveTypeCode): LeaveRequest
    {
        $draft = $this->draftRequestFor($email, $leaveTypeCode);

        return $this->leaveRequestService->submit($draft);
    }

    private function approvedRequestFor(string $email, string $leaveTypeCode): LeaveRequest
    {
        $pending = $this->pendingRequestFor($email, $leaveTypeCode);
        $pending->forceFill([
            'status' => LeaveRequest::STATUS_APPROVED,
        ])->save();

        return $pending->fresh();
    }

    private function nextWorkingDatesForEmployee(Employee $employee, int $count, ?Carbon $startFrom = null): Collection
    {
        /** @var WorkdayPattern $pattern */
        $pattern = WorkdayPattern::query()
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

        $holidays = Holiday::query()
            ->where('company_id', $employee->company_id)
            ->whereHas('holidayCalendar', fn ($query) => $query->where('is_active', true))
            ->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $dates = collect();
        $cursor = ($startFrom ?: now('Asia/Jakarta')->copy()->addDays(30))->copy()->addDay()->startOfDay();

        while ($dates->count() < $count) {
            if ($workingDays->contains($cursor->dayOfWeek) && ! in_array($cursor->toDateString(), $holidays, true)) {
                $dates->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $dates;
    }
}
