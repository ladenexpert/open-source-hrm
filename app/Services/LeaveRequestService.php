<?php

namespace App\Services;

use App\Events\Leave\LeaveRequestApproved;
use App\Events\Leave\LeaveRequestCancelled;
use App\Events\Leave\LeaveRequestRejected;
use App\Events\Leave\LeaveRequestSubmitted;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Services\Leave\LeaveApprovalService;
use App\Models\WorkdayPattern;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LeaveRequestService
{
    private const ATTACHMENT_DISK = 'public';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public function __construct(
        private readonly LeaveCalculationService $leaveCalculationService,
        private readonly LeaveBalanceService $leaveBalanceService,
        private readonly LeaveEntitlementService $leaveEntitlementService,
        private readonly LeaveApprovalService $leaveApprovalService,
    ) {
    }

    public function createDraft(Employee $employee, array $data): LeaveRequest
    {
        return LeaveRequest::query()->create(
            $this->prepareDraftPayload($employee, $data)
        );
    }

    public function updateDraft(LeaveRequest $leaveRequest, array $data): LeaveRequest
    {
        if (! $leaveRequest->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft leave requests can be edited.',
            ]);
        }

        $leaveRequest->fill($this->prepareDraftPayload($leaveRequest->employee()->firstOrFail(), $data));
        $leaveRequest->save();

        return $leaveRequest->fresh([
            'company',
            'employee',
            'leaveType',
            'leaveEntitlement',
            'attachment',
        ]);
    }

    public function submit(LeaveRequest $leaveRequest, ?UploadedFile $attachment = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $attachment): LeaveRequest {
            $leaveRequest = $this->lockLeaveRequest($leaveRequest);

            if (! $leaveRequest->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft leave requests can be submitted.',
                ]);
            }

            $employee = $leaveRequest->employee;
            $leaveType = $leaveRequest->leaveType;

            if (! $employee instanceof Employee || ! $leaveType instanceof LeaveType) {
                throw ValidationException::withMessages([
                    'leave_request' => 'The leave request is missing its employee or leave type relationship.',
                ]);
            }

            $hasExistingAttachment = $leaveRequest->attachment()->exists();

            if ($leaveType->requires_attachment && ! $attachment instanceof UploadedFile && ! $hasExistingAttachment) {
                throw ValidationException::withMessages([
                    'attachment' => 'An attachment is required before this leave request can be submitted.',
                ]);
            }

            $this->checkOverlap($leaveRequest);
            $this->checkBalance($employee, $leaveType, (float) $leaveRequest->requested_days, $leaveRequest->start_date);

            $entitlement = $this->leaveEntitlementService->getActiveEntitlement(
                $employee,
                $leaveType,
                $leaveRequest->start_date->copy()->startOfDay(),
            );

            if (! $entitlement) {
                Log::warning('No active leave entitlement found for submitted leave request.', [
                    'leave_request_id' => $leaveRequest->getKey(),
                    'employee_id' => $employee->getKey(),
                    'leave_type_id' => $leaveType->getKey(),
                    'company_id' => $employee->company_id,
                ]);
            }

            $leaveRequest->forceFill([
                'leave_entitlement_id' => $entitlement?->getKey(),
                'status' => LeaveRequest::STATUS_PENDING,
                'submitted_at' => now(),
            ])->save();

            $this->leaveApprovalService->initiateApproval($leaveRequest);

            if ($attachment instanceof UploadedFile) {
                $this->storeAttachment($leaveRequest, $attachment, $employee);
            }

            $leaveRequest = $leaveRequest->fresh([
                'company',
                'employee',
                'leaveType',
                'leaveEntitlement',
                'attachment',
            ]);

            event(new LeaveRequestSubmitted($leaveRequest, $employee));

            return $leaveRequest;
        });
    }

    public function approve(LeaveRequest $leaveRequest, Employee $approver): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $approver): LeaveRequest {
            $leaveRequest = $this->lockLeaveRequest($leaveRequest);

            if (! $leaveRequest->isPending()) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending leave requests can be approved.',
                ]);
            }

            if ($this->hasLeaveTakenTransaction($leaveRequest)) {
                throw ValidationException::withMessages([
                    'leave_request' => 'A leave deduction has already been recorded for this request.',
                ]);
            }

            $leaveRequest->forceFill([
                'status' => LeaveRequest::STATUS_APPROVED,
                'rejection_reason' => null,
            ])->save();

            $entitlement = $leaveRequest->leaveEntitlement;

            if ($entitlement && (float) $leaveRequest->requested_days > 0) {
                $this->leaveBalanceService->deductBalance(
                    $entitlement,
                    (float) $leaveRequest->requested_days,
                    LeaveRequest::class,
                    (int) $leaveRequest->getKey(),
                    sprintf('Approved leave request #%d.', $leaveRequest->getKey()),
                );
            }

            $leaveRequest = $leaveRequest->fresh([
                'company',
                'employee',
                'leaveType',
                'leaveEntitlement',
                'attachment',
                'cancelledBy',
                'approvalRequest.logs.actor',
                'approvalRequest.steps.approver',
                'approvalRequest.steps.workflowStep',
                'approvalRequest.workflow.steps',
            ]);

            event(new LeaveRequestApproved($leaveRequest, $approver));

            return $leaveRequest;
        });
    }

    public function reject(LeaveRequest $leaveRequest, Employee $approver, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $approver, $reason): LeaveRequest {
            $leaveRequest = $this->lockLeaveRequest($leaveRequest);

            if (! $leaveRequest->isPending()) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending leave requests can be rejected.',
                ]);
            }

            $leaveRequest->forceFill([
                'status' => LeaveRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ])->save();

            $leaveRequest = $leaveRequest->fresh([
                'company',
                'employee',
                'leaveType',
                'leaveEntitlement',
                'attachment',
                'cancelledBy',
                'approvalRequest.logs.actor',
                'approvalRequest.steps.approver',
                'approvalRequest.steps.workflowStep',
                'approvalRequest.workflow.steps',
            ]);

            event(new LeaveRequestRejected($leaveRequest, $approver));

            return $leaveRequest;
        });
    }

    public function cancel(LeaveRequest $leaveRequest, Employee $actor, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actor, $reason): LeaveRequest {
            $leaveRequest = $this->lockLeaveRequest($leaveRequest);

            if (! $leaveRequest->isCancellable()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or pending leave requests can be cancelled.',
                ]);
            }

            if ((int) $leaveRequest->company_id !== (int) $actor->getEffectiveCompanyId()) {
                throw ValidationException::withMessages([
                    'company_id' => 'Leave requests cannot be cancelled across company boundaries.',
                ]);
            }

            if ((int) $leaveRequest->employee_id !== (int) $actor->getKey() && ! $actor->canManageHrMasterData()) {
                throw ValidationException::withMessages([
                    'employee_id' => 'You are not allowed to cancel this leave request.',
                ]);
            }

            $leaveRequest->forceFill([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
                'cancellation_reason' => $reason,
            ])->save();

            $this->leaveApprovalService->cancelPendingApproval($leaveRequest, $actor, $reason);

            $leaveRequest = $leaveRequest->fresh([
                'company',
                'employee',
                'leaveType',
                'leaveEntitlement',
                'attachment',
                'cancelledBy',
                'approvalRequest.logs.actor',
                'approvalRequest.steps.approver',
                'approvalRequest.steps.workflowStep',
                'approvalRequest.workflow.steps',
            ]);

            event(new LeaveRequestCancelled($leaveRequest, $actor));

            return $leaveRequest;
        });
    }

    public function cancelApproved(LeaveRequest $leaveRequest, Employee $adminActor, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $adminActor, $reason): LeaveRequest {
            $leaveRequest = $this->lockLeaveRequest($leaveRequest);

            if (! $leaveRequest->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved leave requests can be cancelled through this action.',
                ]);
            }

            if (! Gate::forUser($adminActor)->allows('cancelApproved', $leaveRequest)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'You are not allowed to cancel this approved leave request.',
                ]);
            }

            $leaveRequest->forceFill([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $adminActor->getKey(),
                'cancellation_reason' => $reason,
            ])->save();

            $entitlement = $leaveRequest->leaveEntitlement;

            if ($entitlement && (float) $leaveRequest->requested_days > 0) {
                if ($this->hasRestoreTransaction($leaveRequest)) {
                    throw ValidationException::withMessages([
                        'leave_request' => 'The approved leave request has already been restored.',
                    ]);
                }

                if (! $this->hasLeaveTakenTransaction($leaveRequest)) {
                    Log::warning('Skipping leave balance restore because no prior leave deduction exists.', [
                        'leave_request_id' => $leaveRequest->getKey(),
                        'employee_id' => $leaveRequest->employee_id,
                        'leave_entitlement_id' => $leaveRequest->leave_entitlement_id,
                    ]);
                } else {
                    $this->leaveBalanceService->restoreBalance(
                        $entitlement,
                        (float) $leaveRequest->requested_days,
                        LeaveRequest::class,
                        (int) $leaveRequest->getKey(),
                        sprintf('Cancelled approved leave request #%d.', $leaveRequest->getKey()),
                    );
                }
            } elseif ((float) $leaveRequest->requested_days > 0) {
                Log::warning('Skipping leave balance restore because the approved leave request has no linked entitlement.', [
                    'leave_request_id' => $leaveRequest->getKey(),
                    'employee_id' => $leaveRequest->employee_id,
                    'company_id' => $leaveRequest->company_id,
                ]);
            }

            $leaveRequest = $leaveRequest->fresh([
                'company',
                'employee',
                'leaveType',
                'leaveEntitlement',
                'attachment',
                'cancelledBy',
                'approvalRequest.logs.actor',
                'approvalRequest.steps.approver',
                'approvalRequest.steps.workflowStep',
                'approvalRequest.workflow.steps',
            ]);

            event(new LeaveRequestCancelled($leaveRequest, $adminActor));

            return $leaveRequest;
        });
    }

    public function storeAttachment(LeaveRequest $leaveRequest, UploadedFile $attachment, ?Employee $actor = null): LeaveRequestAttachment
    {
        $this->validateAttachment($attachment);

        $existingAttachment = $leaveRequest->attachment()->first();

        if ($existingAttachment instanceof LeaveRequestAttachment) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($existingAttachment->path);
            $existingAttachment->delete();
        }

        $storedPath = $attachment->store(
            "leave-requests/{$leaveRequest->company_id}/{$leaveRequest->getKey()}",
            self::ATTACHMENT_DISK,
        );

        return LeaveRequestAttachment::query()->create([
            'company_id' => $leaveRequest->company_id,
            'leave_request_id' => $leaveRequest->getKey(),
            'path' => $storedPath,
            'original_filename' => $attachment->getClientOriginalName(),
            'mime_type' => $attachment->getMimeType(),
            'size_bytes' => $attachment->getSize(),
            'uploaded_by' => $actor?->getKey() ?? $leaveRequest->employee_id,
        ]);
    }

    private function prepareDraftPayload(Employee $employee, array $data): array
    {
        $leaveType = $this->resolveLeaveType($employee, (int) ($data['leave_type_id'] ?? 0));
        $startDate = $this->parseRequiredDate($data, 'start_date');
        $endDate = $this->parseRequiredDate($data, 'end_date');

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after the start date.',
            ]);
        }

        $isHalfDay = (bool) ($data['is_half_day'] ?? false);
        $halfDayType = $data['half_day_type'] ?? null;

        $this->validateHalfDaySelection($leaveType, $startDate, $endDate, $isHalfDay, $halfDayType);

        if (! $isHalfDay) {
            $halfDayType = null;
        }

        $requestedDays = $this->leaveCalculationService->calculateDays(
            $startDate,
            $endDate,
            $this->resolveWorkdayDays($employee),
            $this->resolveHolidayDates($employee, $startDate, $endDate),
            $isHalfDay,
            $halfDayType,
        );

        if ($requestedDays <= 0) {
            throw ValidationException::withMessages([
                'start_date' => 'The selected period contains no working days.',
            ]);
        }

        return [
            'company_id' => $employee->getEffectiveCompanyId(),
            'employee_id' => $employee->getKey(),
            'leave_type_id' => $leaveType->getKey(),
            'leave_entitlement_id' => null,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_half_day' => $isHalfDay,
            'half_day_type' => $halfDayType,
            'requested_days' => $requestedDays,
            'reason' => $data['reason'] ?? null,
            'status' => LeaveRequest::STATUS_DRAFT,
            'submitted_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ];
    }

    private function resolveLeaveType(Employee $employee, int $leaveTypeId): LeaveType
    {
        $leaveType = LeaveType::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->active()
            ->find($leaveTypeId);

        if (! $leaveType instanceof LeaveType) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'The selected leave type is invalid.',
            ]);
        }

        return $leaveType;
    }

    private function parseRequiredDate(array $data, string $field): Carbon
    {
        if (blank($data[$field] ?? null)) {
            throw ValidationException::withMessages([
                $field => 'This field is required.',
            ]);
        }

        return Carbon::parse($data[$field])->startOfDay();
    }

    private function validateHalfDaySelection(
        LeaveType $leaveType,
        Carbon $startDate,
        Carbon $endDate,
        bool $isHalfDay,
        ?string $halfDayType,
    ): void {
        if (! $isHalfDay) {
            return;
        }

        if (! $leaveType->allow_half_day) {
            throw ValidationException::withMessages([
                'is_half_day' => 'The selected leave type does not allow half-day requests.',
            ]);
        }

        if (! $startDate->equalTo($endDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'Half-day leave requests must start and end on the same day.',
            ]);
        }

        if (! in_array($halfDayType, [
            LeaveRequest::HALF_DAY_FIRST,
            LeaveRequest::HALF_DAY_SECOND,
        ], true)) {
            throw ValidationException::withMessages([
                'half_day_type' => 'Please choose whether the request is for the first half or second half of the day.',
            ]);
        }
    }

    private function resolveWorkdayDays(Employee $employee): Collection
    {
        $pattern = WorkdayPattern::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->with('days')
            ->first();

        if (! $pattern instanceof WorkdayPattern) {
            throw ValidationException::withMessages([
                'workday_pattern' => 'No active workday pattern is configured for this employee\'s company.',
            ]);
        }

        return $pattern->days
            ->where('is_working_day', true)
            ->map(function ($day): int {
                $dayOfWeek = (int) $day->day_of_week;

                return $dayOfWeek === 7 ? 0 : $dayOfWeek;
            })
            ->values();
    }

    private function resolveHolidayDates(Employee $employee, Carbon $startDate, Carbon $endDate): Collection
    {
        return Holiday::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('holidayCalendar', function (Builder $query) use ($startDate, $endDate): void {
                $query->where('is_active', true)
                    ->whereBetween('year', [$startDate->year, $endDate->year]);
            })
            ->pluck('date')
            ->map(fn ($date): Carbon => Carbon::parse($date)->startOfDay())
            ->values();
    }

    private function checkOverlap(LeaveRequest $leaveRequest): void
    {
        $overlapExists = LeaveRequest::query()
            ->forCompany($leaveRequest->company_id)
            ->where('employee_id', $leaveRequest->employee_id)
            ->active()
            ->whereKeyNot($leaveRequest->getKey())
            ->where(function (Builder $query) use ($leaveRequest): void {
                $query
                    ->whereDate('start_date', '<=', $leaveRequest->end_date->toDateString())
                    ->whereDate('end_date', '>=', $leaveRequest->start_date->toDateString());
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'start_date' => 'The selected leave period overlaps with an existing pending or approved leave request.',
            ]);
        }
    }

    private function checkBalance(Employee $employee, LeaveType $leaveType, float $requestedDays, Carbon $referenceDate): void
    {
        if (! $leaveType->is_paid) {
            return;
        }

        $entitlement = $this->leaveBalanceService->getBalance($employee, $leaveType, $referenceDate->year);
        $availableBalance = $entitlement ? (float) $entitlement->remaining_days : 0.0;

        if ($availableBalance < $requestedDays) {
            throw ValidationException::withMessages([
                'requested_days' => 'The requested leave exceeds the available paid leave balance.',
            ]);
        }
    }

    private function validateAttachment(UploadedFile $attachment): void
    {
        $mimeType = $attachment->getMimeType();

        if (! in_array($mimeType, self::ALLOWED_ATTACHMENT_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'attachment' => 'Attachments must be a PDF, JPG, or PNG file.',
            ]);
        }
    }

    private function lockLeaveRequest(LeaveRequest $leaveRequest): LeaveRequest
    {
        return LeaveRequest::query()
            ->with([
                'company',
                'employee',
                'leaveType',
                'leaveEntitlement',
                'attachment',
                'cancelledBy',
                'approvalRequest.logs.actor',
                'approvalRequest.steps.approver',
                'approvalRequest.steps.workflowStep',
                'approvalRequest.workflow.steps',
            ])
            ->whereKey($leaveRequest->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function hasLeaveTakenTransaction(LeaveRequest $leaveRequest): bool
    {
        return LeaveTransaction::query()
            ->where('reference_type', LeaveRequest::class)
            ->where('reference_id', $leaveRequest->getKey())
            ->where('transaction_type', LeaveTransaction::TYPE_LEAVE_TAKEN)
            ->exists();
    }

    private function hasRestoreTransaction(LeaveRequest $leaveRequest): bool
    {
        return LeaveTransaction::query()
            ->where('reference_type', LeaveRequest::class)
            ->where('reference_id', $leaveRequest->getKey())
            ->where('transaction_type', LeaveTransaction::TYPE_RESTORE)
            ->exists();
    }
}
