<?php

namespace App\Services;

use App\Enums\ApprovalModuleType;
use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OvertimeRequestService
{
    public function __construct(
        private readonly ApprovalRequestService $approvalRequestService,
        private readonly ApprovalActionService $approvalActionService,
        private readonly OvertimeCalculationService $overtimeCalculationService,
    ) {
    }

    public function createDraft(Employee $employee, array $payload, ?Employee $actor = null): OvertimeRequest
    {
        $overtimeDate = $this->parseRequiredDate($payload, 'overtime_date');
        $this->assertNoExistingActiveRequest($employee, $overtimeDate);

        $summary = AttendanceSummary::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->forDate($overtimeDate)
            ->first();

        $requestedStartAt = $this->parseOptionalDateTime($payload['requested_start_at'] ?? null);
        $requestedEndAt = $this->parseOptionalDateTime($payload['requested_end_at'] ?? null);
        $requestedMinutes = $this->resolveRequestedMinutes(
            $payload['requested_minutes'] ?? null,
            $requestedStartAt,
            $requestedEndAt,
        );
        $auditActor = $actor instanceof Employee ? $actor : $employee;

        $request = OvertimeRequest::query()->create([
            'company_id' => $employee->getEffectiveCompanyId(),
            'employee_id' => $employee->getKey(),
            'attendance_summary_id' => $summary?->getKey(),
            'overtime_date' => $overtimeDate->toDateString(),
            'requested_start_at' => $requestedStartAt,
            'requested_end_at' => $requestedEndAt,
            'requested_minutes' => $requestedMinutes,
            'reason' => $this->resolveOptionalString($payload['reason'] ?? null),
            'status' => OvertimeRequest::STATUS_DRAFT,
            'metadata' => $this->resolveOptionalArray($payload['metadata'] ?? null),
            'created_by' => $auditActor->getKey(),
            'updated_by' => $auditActor->getKey(),
        ]);

        return $this->freshRequest($request);
    }

    public function submit(OvertimeRequest $overtimeRequest, Employee $submittedBy): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequest, $submittedBy): OvertimeRequest {
            $overtimeRequest = $this->lockRequest($overtimeRequest);

            if (! $overtimeRequest->canSubmit()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft overtime requests can be submitted.',
                ]);
            }

            $this->assertCompanyScopedActor($overtimeRequest, $submittedBy);
            $this->assertOwnerOrHrManager($overtimeRequest, $submittedBy, 'You are not allowed to submit this overtime request.');

            if (filled($overtimeRequest->approval_request_id)) {
                throw new RuntimeException('An approval request already exists for this overtime request.');
            }

            $approvalRequest = $this->approvalRequestService->submit(
                $overtimeRequest,
                $submittedBy,
                ApprovalModuleType::OVERTIME->value,
                $overtimeRequest->employee,
                $this->buildSummary($overtimeRequest),
                $this->buildPayload($overtimeRequest),
            );

            $overtimeRequest->forceFill([
                'status' => OvertimeRequest::STATUS_SUBMITTED,
                'submitted_at' => now(config('app.timezone')),
                'submitted_by' => $submittedBy->getKey(),
                'approval_request_id' => $approvalRequest->getKey(),
                'updated_by' => $submittedBy->getKey(),
            ])->save();

            return $this->freshRequest($overtimeRequest);
        });
    }

    public function approve(OvertimeRequest $overtimeRequest, Employee $approver, array $payload = []): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequest, $approver, $payload): OvertimeRequest {
            $overtimeRequest = $this->lockRequest($overtimeRequest);

            if (! $overtimeRequest->isSubmitted()) {
                throw ValidationException::withMessages([
                    'status' => 'Only submitted overtime requests can be approved.',
                ]);
            }

            $this->assertCompanyScopedActor($overtimeRequest, $approver);
            $this->assertCanFinalizeDecision($overtimeRequest, $approver, 'approve');

            $approvedMinutes = $this->resolveApprovedMinutes($payload, $overtimeRequest);

            $overtimeRequest->forceFill([
                'status' => OvertimeRequest::STATUS_APPROVED,
                'approved_minutes' => $approvedMinutes,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(config('app.timezone')),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'updated_by' => $approver->getKey(),
            ])->save();

            $this->syncApprovalRequestStatus(
                $overtimeRequest->approvalRequest,
                ApprovalRequestStatus::APPROVED,
                $approver,
                'Overtime request approved.',
            );

            $this->overtimeCalculationService->calculateForRequest($overtimeRequest);

            return $this->freshRequest($overtimeRequest);
        });
    }

    public function reject(OvertimeRequest $overtimeRequest, Employee $rejector, ?string $reason = null): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequest, $rejector, $reason): OvertimeRequest {
            $overtimeRequest = $this->lockRequest($overtimeRequest);

            if (! $overtimeRequest->isSubmitted()) {
                throw ValidationException::withMessages([
                    'status' => 'Only submitted overtime requests can be rejected.',
                ]);
            }

            $this->assertCompanyScopedActor($overtimeRequest, $rejector);
            $this->assertCanFinalizeDecision($overtimeRequest, $rejector, 'reject');

            $overtimeRequest->forceFill([
                'status' => OvertimeRequest::STATUS_REJECTED,
                'approved_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => $rejector->getKey(),
                'rejected_at' => now(config('app.timezone')),
                'rejection_reason' => $reason,
                'updated_by' => $rejector->getKey(),
            ])->save();

            $this->syncApprovalRequestStatus(
                $overtimeRequest->approvalRequest,
                ApprovalRequestStatus::REJECTED,
                $rejector,
                $reason ?: 'Overtime request rejected.',
            );

            return $this->freshRequest($overtimeRequest);
        });
    }

    public function cancel(OvertimeRequest $overtimeRequest, Employee $cancelledBy, ?string $reason = null): OvertimeRequest
    {
        return DB::transaction(function () use ($overtimeRequest, $cancelledBy, $reason): OvertimeRequest {
            $overtimeRequest = $this->lockRequest($overtimeRequest);

            if (! $overtimeRequest->canCancel()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or submitted overtime requests can be cancelled.',
                ]);
            }

            $this->assertCompanyScopedActor($overtimeRequest, $cancelledBy);
            $this->assertOwnerOrHrManager($overtimeRequest, $cancelledBy, 'You are not allowed to cancel this overtime request.');

            $overtimeRequest->forceFill([
                'status' => OvertimeRequest::STATUS_CANCELLED,
                'cancelled_by' => $cancelledBy->getKey(),
                'cancelled_at' => now(config('app.timezone')),
                'cancellation_reason' => $reason,
                'updated_by' => $cancelledBy->getKey(),
            ])->save();

            if ($overtimeRequest->approvalRequest instanceof ApprovalRequest) {
                $this->approvalActionService->cancelRequest(
                    $overtimeRequest->approvalRequest,
                    $cancelledBy,
                    $reason ?: 'Overtime request cancelled.',
                );
            }

            return $this->freshRequest($overtimeRequest);
        });
    }

    public function processApproval(
        ApprovalRequest $approvalRequest,
        Employee $approver,
        string $decision,
        ?string $notes = null,
        array $payload = [],
    ): ApprovalRequest {
        return DB::transaction(function () use ($approvalRequest, $approver, $decision, $notes, $payload): ApprovalRequest {
            $overtimeRequest = $this->resolveRequest($approvalRequest);

            $updatedApprovalRequest = match ($decision) {
                'approved' => $this->approvalActionService->approveCurrentStep($approvalRequest, $approver, $notes),
                'rejected' => $this->approvalActionService->rejectCurrentStep($approvalRequest, $approver, $notes),
                default => throw new RuntimeException("Unsupported overtime approval decision [{$decision}]."),
            };

            if ($decision === 'approved' && $updatedApprovalRequest->status === ApprovalRequestStatus::APPROVED) {
                $this->approve($overtimeRequest, $approver, $payload);
            }

            if ($decision === 'rejected') {
                $this->reject($overtimeRequest, $approver, $notes);
            }

            return $updatedApprovalRequest->fresh([
                'workflow.steps',
                'steps.workflowStep',
                'steps.approver',
                'logs.actor',
                'requester',
                'employeeSubject',
                'approvable',
            ]);
        });
    }

    private function resolveRequest(ApprovalRequest $approvalRequest): OvertimeRequest
    {
        $approvalRequest->loadMissing('approvable');
        $approvable = $approvalRequest->approvable;

        if (! $approvable instanceof OvertimeRequest) {
            throw new RuntimeException('The approval request is not linked to an overtime request.');
        }

        return $approvable;
    }

    private function syncApprovalRequestStatus(
        ?ApprovalRequest $approvalRequest,
        ApprovalRequestStatus $status,
        Employee $actor,
        ?string $comments = null,
    ): void {
        if (! $approvalRequest instanceof ApprovalRequest || $approvalRequest->status === $status) {
            return;
        }

        $approvalRequest->steps()
            ->where('status', 'pending')
            ->update([
                'status' => $status === ApprovalRequestStatus::APPROVED ? 'approved' : 'rejected',
                'acted_at' => now(config('app.timezone')),
                'comments' => $comments,
                'updated_at' => now(config('app.timezone')),
            ]);

        $approvalRequest->forceFill([
            'status' => $status,
            'completed_at' => now(config('app.timezone')),
            'current_step_order' => null,
        ])->save();

        $approvalRequest->logs()->create([
            'actor_id' => $actor->getKey(),
            'action' => $status === ApprovalRequestStatus::APPROVED ? 'fully_approved' : 'rejected',
            'comments' => $comments,
            'metadata' => [
                'overtime_request_id' => $approvalRequest->approvable_id,
                'synchronized_by_service' => true,
            ],
        ]);
    }

    private function lockRequest(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        return OvertimeRequest::query()
            ->with([
                'company',
                'employee',
                'attendanceSummary.attendancePolicy',
                'approvalRequest.logs.actor',
                'approvalRequest.steps.approver',
                'approvalRequest.steps.workflowStep',
                'approvalRequest.workflow.steps',
                'calculation',
                'submittedBy',
                'approvedBy',
                'rejectedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ])
            ->whereKey($overtimeRequest->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function freshRequest(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        return $overtimeRequest->fresh([
            'company',
            'employee',
            'attendanceSummary.attendancePolicy',
            'approvalRequest.logs.actor',
            'approvalRequest.steps.approver',
            'approvalRequest.steps.workflowStep',
            'approvalRequest.workflow.steps',
            'calculation.attendanceSummary',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'cancelledBy',
            'createdBy',
            'updatedBy',
        ]);
    }

    private function buildSummary(OvertimeRequest $overtimeRequest): string
    {
        $overtimeRequest->loadMissing('employee');

        return sprintf(
            '%s submitted an overtime request for %s.',
            $overtimeRequest->employee?->full_name ?? 'Unknown employee',
            $overtimeRequest->overtime_date->toDateString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(OvertimeRequest $overtimeRequest): array
    {
        return [
            'overtime_request_id' => $overtimeRequest->getKey(),
            'overtime_date' => $overtimeRequest->overtime_date->toDateString(),
            'requested_start_at' => $overtimeRequest->requested_start_at?->toIso8601String(),
            'requested_end_at' => $overtimeRequest->requested_end_at?->toIso8601String(),
            'requested_minutes' => $overtimeRequest->requested_minutes,
            'reason' => $overtimeRequest->reason,
        ];
    }

    private function assertCompanyScopedActor(OvertimeRequest $overtimeRequest, Employee $actor): void
    {
        if ((int) $actor->getEffectiveCompanyId() !== (int) $overtimeRequest->company_id) {
            throw ValidationException::withMessages([
                'company_id' => 'Overtime requests cannot be managed across company boundaries.',
            ]);
        }
    }

    private function assertOwnerOrHrManager(OvertimeRequest $overtimeRequest, Employee $actor, string $message): void
    {
        if ((int) $overtimeRequest->employee_id === (int) $actor->getKey()) {
            return;
        }

        if ($actor->canManageHrMasterData() && $actor->canAccessCompany($overtimeRequest->company_id)) {
            return;
        }

        throw ValidationException::withMessages([
            'employee_id' => $message,
        ]);
    }

    private function assertCanFinalizeDecision(OvertimeRequest $overtimeRequest, Employee $actor, string $decision): void
    {
        $approvalRequest = $overtimeRequest->approvalRequest;

        if (
            $approvalRequest instanceof ApprovalRequest
            && in_array($approvalRequest->status, [ApprovalRequestStatus::APPROVED, ApprovalRequestStatus::REJECTED], true)
        ) {
            return;
        }

        if ($actor->canManageHrMasterData() && $actor->canAccessCompany($overtimeRequest->company_id)) {
            return;
        }

        $canAct = $decision === 'approve'
            ? ($approvalRequest instanceof ApprovalRequest && $this->approvalActionService->canApprove($approvalRequest, $actor))
            : ($approvalRequest instanceof ApprovalRequest && $this->approvalActionService->canReject($approvalRequest, $actor));

        if ($canAct) {
            return;
        }

        throw ValidationException::withMessages([
            'approval_request_id' => 'You are not allowed to process this overtime request.',
        ]);
    }

    private function assertNoExistingActiveRequest(Employee $employee, Carbon $overtimeDate): void
    {
        $hasExisting = OvertimeRequest::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->forDate($overtimeDate)
            ->whereNotIn('status', [
                OvertimeRequest::STATUS_REJECTED,
                OvertimeRequest::STATUS_CANCELLED,
            ])
            ->exists();

        if ($hasExisting) {
            throw ValidationException::withMessages([
                'overtime_date' => 'An active overtime request already exists for this employee and date.',
            ]);
        }
    }

    private function parseRequiredDate(array $payload, string $field): Carbon
    {
        if (blank($payload[$field] ?? null)) {
            throw ValidationException::withMessages([
                $field => 'This field is required.',
            ]);
        }

        return Carbon::parse($payload[$field], config('app.timezone'))->startOfDay();
    }

    private function parseOptionalDateTime(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value, config('app.timezone'));
    }

    private function resolveRequestedMinutes(mixed $value, ?Carbon $requestedStartAt, ?Carbon $requestedEndAt): ?int
    {
        if (filled($value)) {
            $minutes = (int) $value;

            if ($minutes < 0) {
                throw ValidationException::withMessages([
                    'requested_minutes' => 'Requested minutes must be zero or greater.',
                ]);
            }

            return $minutes;
        }

        if ($requestedStartAt instanceof Carbon && $requestedEndAt instanceof Carbon) {
            if (! $requestedEndAt->greaterThan($requestedStartAt)) {
                throw ValidationException::withMessages([
                    'requested_end_at' => 'Requested end time must be after the requested start time.',
                ]);
            }

            return $requestedStartAt->diffInMinutes($requestedEndAt);
        }

        return null;
    }

    private function resolveApprovedMinutes(array $payload, OvertimeRequest $overtimeRequest): ?int
    {
        if (! array_key_exists('approved_minutes', $payload) || blank($payload['approved_minutes'])) {
            return $overtimeRequest->requested_minutes;
        }

        $approvedMinutes = (int) $payload['approved_minutes'];

        if ($approvedMinutes < 0) {
            throw ValidationException::withMessages([
                'approved_minutes' => 'Approved minutes must be zero or greater.',
            ]);
        }

        return $approvedMinutes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveOptionalArray(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    private function resolveOptionalString(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
