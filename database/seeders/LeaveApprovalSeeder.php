<?php

namespace Database\Seeders;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\WorkdayPattern;
use App\Services\Leave\LeaveApprovalService;
use App\Services\LeaveRequestService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class LeaveApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $leaveApprovalService = app(LeaveApprovalService::class);
        $leaveRequestService = app(LeaveRequestService::class);

        LeaveRequest::query()
            ->with(['employee', 'leaveType', 'approvalRequest'])
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->orderBy('id')
            ->get()
            ->each(function (LeaveRequest $leaveRequest) use ($leaveApprovalService): void {
                if ($leaveRequest->approvalRequest !== null) {
                    return;
                }

                if (! $leaveRequest->employee instanceof Employee || ! $leaveRequest->leaveType instanceof LeaveType) {
                    return;
                }

                $leaveApprovalService->initiateApproval($leaveRequest);
            });

        Company::query()
            ->orderBy('id')
            ->get()
            ->each(function (Company $company) use ($leaveApprovalService, $leaveRequestService): void {
                $hasApprovedRequest = LeaveRequest::query()
                    ->where('company_id', $company->id)
                    ->where('status', LeaveRequest::STATUS_APPROVED)
                    ->exists();

                if ($hasApprovedRequest) {
                    return;
                }

                $employee = $this->resolveEligibleEmployee($company);
                $leaveType = $this->resolveAnnualLeaveType($company);

                if (! $employee instanceof Employee || ! $leaveType instanceof LeaveType) {
                    return;
                }

                $dates = $this->nextWorkingDates($company, 1, now('Asia/Jakarta')->copy()->addDays(60));

                if ($dates->isEmpty()) {
                    return;
                }

                $request = $leaveRequestService->createDraft($employee, [
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $dates->first()->toDateString(),
                    'end_date' => $dates->first()->toDateString(),
                    'reason' => 'Seeded approved leave request.',
                ]);

                $request = $leaveRequestService->submit($request);
                $approvalRequest = $request->approvalRequest()->first();

                if (! $approvalRequest instanceof ApprovalRequest) {
                    return;
                }

                $this->approveAllPendingSteps($approvalRequest, $leaveApprovalService);
            });
    }

    private function approveAllPendingSteps(ApprovalRequest $approvalRequest, LeaveApprovalService $leaveApprovalService): void
    {
        while ($approvalRequest->status === ApprovalRequestStatus::PENDING && filled($approvalRequest->current_step_order)) {
            $pendingSteps = $approvalRequest->steps()
                ->with('approver')
                ->where('step_order', $approvalRequest->current_step_order)
                ->where('status', ApprovalStepStatus::PENDING)
                ->orderBy('id')
                ->get();

            $approvableSteps = $pendingSteps->filter(fn ($step) => $step->approver instanceof Employee)->values();

            if ($approvableSteps->isEmpty()) {
                break;
            }

            foreach ($approvableSteps as $step) {
                $leaveApprovalService->processApproval(
                    $approvalRequest,
                    $step->approver,
                    'approved',
                    'Seeded approval progression.',
                );

                $approvalRequest = $approvalRequest->fresh([
                    'steps.approver',
                    'logs.actor',
                    'approvable',
                ]);

                if ($approvalRequest->status !== ApprovalRequestStatus::PENDING) {
                    break;
                }
            }
        }
    }

    private function resolveEligibleEmployee(Company $company): ?Employee
    {
        return Employee::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('direct_supervisor_id')
            ->whereHas('department', fn ($query) => $query->whereNotNull('manager_id'))
            ->orderBy('id')
            ->first();
    }

    private function resolveAnnualLeaveType(Company $company): ?LeaveType
    {
        return LeaveType::query()
            ->where('company_id', $company->id)
            ->where('code', 'ANNUAL')
            ->where('is_active', true)
            ->first();
    }

    private function nextWorkingDates(Company $company, int $count, Carbon $startFrom): Collection
    {
        $workdayDays = $this->resolveWorkdayDays($company);
        $holidayDates = Holiday::query()
            ->where('company_id', $company->id)
            ->whereHas('holidayCalendar', fn ($query) => $query->where('is_active', true))
            ->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $dates = collect();
        $cursor = $startFrom->copy()->startOfDay();

        while ($dates->count() < $count) {
            if ($workdayDays->contains($cursor->dayOfWeek) && ! in_array($cursor->toDateString(), $holidayDates, true)) {
                $dates->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $dates;
    }

    private function resolveWorkdayDays(Company $company): Collection
    {
        /** @var WorkdayPattern $pattern */
        $pattern = WorkdayPattern::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->with('days')
            ->firstOrFail();

        return $pattern->days
            ->where('is_working_day', true)
            ->map(function ($day): int {
                $dayOfWeek = (int) $day->day_of_week;

                return $dayOfWeek === 7 ? 0 : $dayOfWeek;
            })
            ->values();
    }
}
