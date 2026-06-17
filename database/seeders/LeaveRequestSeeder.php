<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\LeaveType;
use App\Models\WorkdayPattern;
use App\Services\LeaveCalculationService;
use App\Services\LeaveEntitlementService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class LeaveRequestSeeder extends Seeder
{
    public function run(): void
    {
        $leaveCalculationService = app(LeaveCalculationService::class);
        $leaveEntitlementService = app(LeaveEntitlementService::class);

        Company::query()
            ->orderBy('id')
            ->get()
            ->each(function (Company $company) use ($leaveCalculationService, $leaveEntitlementService): void {
                $employees = Employee::query()
                    ->where('company_id', $company->id)
                    ->orderBy('id')
                    ->get();

                if ($employees->isEmpty()) {
                    return;
                }

                $annualLeave = LeaveType::query()
                    ->where('company_id', $company->id)
                    ->where('code', 'ANNUAL')
                    ->first();

                if (! $annualLeave instanceof LeaveType) {
                    return;
                }

                $sickLeave = LeaveType::query()
                    ->where('company_id', $company->id)
                    ->where('code', 'SICK')
                    ->first();

                $workingDates = $this->nextWorkingDates($company, 3);

                if ($workingDates->count() < 3) {
                    return;
                }

                $workdayDays = $this->resolveWorkdayDays($company);
                $holidayDates = $this->resolveHolidayDates($company, $workingDates->first(), $workingDates->last());

                $employees->each(function (Employee $employee) use (
                    $annualLeave,
                    $sickLeave,
                    $workingDates,
                    $workdayDays,
                    $holidayDates,
                    $leaveCalculationService,
                    $leaveEntitlementService,
                ): void {
                    $pendingDate = $workingDates[0];
                    $draftDate = $workingDates[1];
                    $attachmentDate = $workingDates[2];

                    $pendingDays = $leaveCalculationService->calculateDays(
                        $pendingDate,
                        $pendingDate,
                        $workdayDays,
                        $holidayDates,
                    );

                    $draftDays = $leaveCalculationService->calculateDays(
                        $draftDate,
                        $draftDate,
                        $workdayDays,
                        $holidayDates,
                    );

                    $pendingRequest = LeaveRequest::query()->updateOrCreate(
                        [
                            'company_id' => $employee->company_id,
                            'employee_id' => $employee->id,
                            'leave_type_id' => $annualLeave->id,
                            'start_date' => $pendingDate->toDateString(),
                            'end_date' => $pendingDate->toDateString(),
                        ],
                        [
                            'leave_entitlement_id' => $leaveEntitlementService->getActiveEntitlement($employee, $annualLeave, $pendingDate)?->id,
                            'is_half_day' => false,
                            'half_day_type' => null,
                            'requested_days' => $pendingDays,
                            'reason' => 'Seeded pending leave request.',
                            'status' => LeaveRequest::STATUS_PENDING,
                            'submitted_at' => now(),
                            'cancelled_at' => null,
                            'cancelled_by' => null,
                            'cancellation_reason' => null,
                            'notes' => 'Seeded for admin monitoring.',
                        ],
                    );

                    LeaveRequest::query()->updateOrCreate(
                        [
                            'company_id' => $employee->company_id,
                            'employee_id' => $employee->id,
                            'leave_type_id' => $annualLeave->id,
                            'start_date' => $draftDate->toDateString(),
                            'end_date' => $draftDate->toDateString(),
                        ],
                        [
                            'leave_entitlement_id' => null,
                            'is_half_day' => false,
                            'half_day_type' => null,
                            'requested_days' => $draftDays,
                            'reason' => 'Seeded draft leave request.',
                            'status' => LeaveRequest::STATUS_DRAFT,
                            'submitted_at' => null,
                            'cancelled_at' => null,
                            'cancelled_by' => null,
                            'cancellation_reason' => null,
                            'notes' => 'Seeded draft for portal testing.',
                        ],
                    );

                    if (! $sickLeave instanceof LeaveType || ! $sickLeave->requires_attachment) {
                        return;
                    }

                    $attachmentDays = $leaveCalculationService->calculateDays(
                        $attachmentDate,
                        $attachmentDate,
                        $workdayDays,
                        $holidayDates,
                    );

                    $attachmentRequest = LeaveRequest::query()->updateOrCreate(
                        [
                            'company_id' => $employee->company_id,
                            'employee_id' => $employee->id,
                            'leave_type_id' => $sickLeave->id,
                            'start_date' => $attachmentDate->toDateString(),
                            'end_date' => $attachmentDate->toDateString(),
                        ],
                        [
                            'leave_entitlement_id' => $leaveEntitlementService->getActiveEntitlement($employee, $sickLeave, $attachmentDate)?->id,
                            'is_half_day' => false,
                            'half_day_type' => null,
                            'requested_days' => $attachmentDays,
                            'reason' => 'Seeded attachment-backed leave request.',
                            'status' => LeaveRequest::STATUS_PENDING,
                            'submitted_at' => now(),
                            'cancelled_at' => null,
                            'cancelled_by' => null,
                            'cancellation_reason' => null,
                            'notes' => 'Seeded with placeholder attachment.',
                        ],
                    );

                    LeaveRequestAttachment::query()->updateOrCreate(
                        [
                            'company_id' => $employee->company_id,
                            'leave_request_id' => $attachmentRequest->id,
                        ],
                        [
                            'path' => "seeders/leave-requests/company-{$employee->company_id}/employee-{$employee->id}/medical-note.pdf",
                            'original_filename' => 'medical-note.pdf',
                            'mime_type' => 'application/pdf',
                            'size_bytes' => 1024,
                            'uploaded_by' => $employee->id,
                        ],
                    );

                    $pendingRequest->refresh();
                });
            });
    }

    private function nextWorkingDates(Company $company, int $count): Collection
    {
        $workdayDays = $this->resolveWorkdayDays($company);
        $holidayDates = Holiday::query()
            ->where('company_id', $company->id)
            ->whereHas('holidayCalendar', fn ($query) => $query->where('is_active', true))
            ->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $dates = collect();
        $cursor = now('Asia/Jakarta')->copy()->addDay()->startOfDay();

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

    private function resolveHolidayDates(Company $company, Carbon $startDate, Carbon $endDate): Collection
    {
        return Holiday::query()
            ->where('company_id', $company->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('holidayCalendar', fn ($query) => $query->where('is_active', true))
            ->pluck('date')
            ->map(fn ($date): Carbon => Carbon::parse($date)->startOfDay())
            ->values();
    }
}
