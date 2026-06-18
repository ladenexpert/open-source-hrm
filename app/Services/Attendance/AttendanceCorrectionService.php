<?php

namespace App\Services\Attendance;

use App\Enums\ApprovalModuleType;
use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Services\ApprovalActionService;
use App\Services\ApprovalRequestService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AttendanceCorrectionService
{
    public function __construct(
        private readonly AttendanceCalculationService $attendanceCalculationService,
        private readonly ApprovalRequestService $approvalRequestService,
        private readonly ApprovalActionService $approvalActionService,
    ) {
    }

    public function createDraft(Employee $employee, array $payload): AttendanceCorrection
    {
        $attendanceDate = $this->parseRequiredDate($payload, 'attendance_date');
        $summary = AttendanceSummary::query()
            ->forCompany($employee->getEffectiveCompanyId())
            ->forEmployee($employee)
            ->forDate($attendanceDate)
            ->first();

        $correction = AttendanceCorrection::query()->create([
            'company_id' => $employee->getEffectiveCompanyId(),
            'employee_id' => $employee->getKey(),
            'attendance_summary_id' => $summary?->getKey(),
            'attendance_date' => $attendanceDate->toDateString(),
            'correction_type' => $this->requireString($payload, 'correction_type'),
            'reason' => $this->requireString($payload, 'reason'),
            'requested_clock_in_at' => $this->parseOptionalDateTime($payload['requested_clock_in_at'] ?? null),
            'requested_clock_out_at' => $this->parseOptionalDateTime($payload['requested_clock_out_at'] ?? null),
            'requested_work_location_id' => $this->resolveOptionalInteger($payload['requested_work_location_id'] ?? null),
            'requested_notes' => $this->resolveOptionalString($payload['requested_notes'] ?? null),
            'status' => AttendanceCorrection::STATUS_DRAFT,
            'created_by' => $employee->getKey(),
            'updated_by' => $employee->getKey(),
        ]);

        return $this->freshCorrection($correction);
    }

    public function submit(AttendanceCorrection $correction, Employee $submittedBy): AttendanceCorrection
    {
        return DB::transaction(function () use ($correction, $submittedBy): AttendanceCorrection {
            $correction = $this->lockCorrection($correction);

            if (! $correction->canSubmit()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft attendance corrections can be submitted.',
                ]);
            }

            $this->assertCompanyScopedActor($correction, $submittedBy);
            $this->assertOwnerOrHrManager($correction, $submittedBy, 'You are not allowed to submit this attendance correction.');

            if (filled($correction->approval_request_id)) {
                throw new RuntimeException('An approval request already exists for this attendance correction.');
            }

            $approvalRequest = $this->approvalRequestService->submit(
                $correction,
                $submittedBy,
                ApprovalModuleType::ATTENDANCE_CORRECTION->value,
                $correction->employee,
                $this->buildSummary($correction),
                $this->buildPayload($correction),
            );

            $correction->forceFill([
                'status' => AttendanceCorrection::STATUS_PENDING,
                'submitted_at' => now(config('app.timezone')),
                'submitted_by' => $submittedBy->getKey(),
                'approval_request_id' => $approvalRequest->getKey(),
                'updated_by' => $submittedBy->getKey(),
            ])->save();

            return $this->freshCorrection($correction);
        });
    }

    public function approve(
        AttendanceCorrection $correction,
        Employee $approver,
        array $approvedPayload = [],
    ): AttendanceCorrection {
        return DB::transaction(function () use ($correction, $approver, $approvedPayload): AttendanceCorrection {
            $correction = $this->lockCorrection($correction);

            if (! $correction->isPending()) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending attendance corrections can be approved.',
                ]);
            }

            $this->assertCompanyScopedActor($correction, $approver);
            $this->assertCanFinalizeDecision($correction, $approver, 'approve');

            $correction->forceFill([
                'status' => AttendanceCorrection::STATUS_APPROVED,
                'approved_at' => now(config('app.timezone')),
                'approved_by' => $approver->getKey(),
                'approved_clock_in_at' => $this->resolveApprovedDateTime($approvedPayload, 'approved_clock_in_at', $correction->requested_clock_in_at),
                'approved_clock_out_at' => $this->resolveApprovedDateTime($approvedPayload, 'approved_clock_out_at', $correction->requested_clock_out_at),
                'approved_work_location_id' => $this->resolveApprovedInteger($approvedPayload, 'approved_work_location_id', $correction->requested_work_location_id),
                'approved_notes' => $this->resolveApprovedString($approvedPayload, 'approved_notes', $correction->requested_notes),
                'rejected_at' => null,
                'rejected_by' => null,
                'updated_by' => $approver->getKey(),
            ])->save();

            $this->syncApprovalRequestStatus(
                $correction->approvalRequest,
                ApprovalRequestStatus::APPROVED,
                $approver,
                'Attendance correction approved.',
            );

            $summary = $this->attendanceCalculationService->calculateForEmployeeDate(
                $correction->employee()->firstOrFail(),
                $correction->attendance_date->copy(),
            );

            $correction->forceFill([
                'attendance_summary_id' => $summary->getKey(),
            ])->save();

            return $this->freshCorrection($correction);
        });
    }

    public function reject(
        AttendanceCorrection $correction,
        Employee $rejector,
        ?string $reason = null,
    ): AttendanceCorrection {
        return DB::transaction(function () use ($correction, $rejector, $reason): AttendanceCorrection {
            $correction = $this->lockCorrection($correction);

            if (! $correction->isPending()) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending attendance corrections can be rejected.',
                ]);
            }

            $this->assertCompanyScopedActor($correction, $rejector);
            $this->assertCanFinalizeDecision($correction, $rejector, 'reject');

            $correction->forceFill([
                'status' => AttendanceCorrection::STATUS_REJECTED,
                'approved_at' => null,
                'approved_by' => null,
                'approved_clock_in_at' => null,
                'approved_clock_out_at' => null,
                'approved_work_location_id' => null,
                'approved_notes' => null,
                'rejected_at' => now(config('app.timezone')),
                'rejected_by' => $rejector->getKey(),
                'updated_by' => $rejector->getKey(),
            ])->save();

            $this->syncApprovalRequestStatus(
                $correction->approvalRequest,
                ApprovalRequestStatus::REJECTED,
                $rejector,
                $reason ?: 'Attendance correction rejected.',
            );

            return $this->freshCorrection($correction);
        });
    }

    public function cancel(AttendanceCorrection $correction, Employee $cancelledBy): AttendanceCorrection
    {
        return DB::transaction(function () use ($correction, $cancelledBy): AttendanceCorrection {
            $correction = $this->lockCorrection($correction);

            if (! $correction->canCancel()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or pending attendance corrections can be cancelled.',
                ]);
            }

            $this->assertCompanyScopedActor($correction, $cancelledBy);
            $this->assertOwnerOrHrManager($correction, $cancelledBy, 'You are not allowed to cancel this attendance correction.');

            $correction->forceFill([
                'status' => AttendanceCorrection::STATUS_CANCELLED,
                'cancelled_at' => now(config('app.timezone')),
                'cancelled_by' => $cancelledBy->getKey(),
                'updated_by' => $cancelledBy->getKey(),
            ])->save();

            if ($correction->approvalRequest instanceof ApprovalRequest) {
                $this->approvalActionService->cancelRequest(
                    $correction->approvalRequest,
                    $cancelledBy,
                    'Attendance correction cancelled.',
                );
            }

            return $this->freshCorrection($correction);
        });
    }

    public function processApproval(
        ApprovalRequest $approvalRequest,
        Employee $approver,
        string $decision,
        ?string $notes = null,
        array $approvedPayload = [],
    ): ApprovalRequest {
        return DB::transaction(function () use ($approvalRequest, $approver, $decision, $notes, $approvedPayload): ApprovalRequest {
            $correction = $this->resolveCorrection($approvalRequest);

            $updatedApprovalRequest = match ($decision) {
                'approved' => $this->approvalActionService->approveCurrentStep($approvalRequest, $approver, $notes),
                'rejected' => $this->approvalActionService->rejectCurrentStep($approvalRequest, $approver, $notes),
                default => throw new RuntimeException("Unsupported attendance correction approval decision [{$decision}]."),
            };

            if ($decision === 'approved' && $updatedApprovalRequest->status === ApprovalRequestStatus::APPROVED) {
                $this->approve($correction, $approver, $approvedPayload);
            }

            if ($decision === 'rejected') {
                $this->reject($correction, $approver, $notes);
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

    private function resolveCorrection(ApprovalRequest $approvalRequest): AttendanceCorrection
    {
        $approvalRequest->loadMissing('approvable');
        $approvable = $approvalRequest->approvable;

        if (! $approvable instanceof AttendanceCorrection) {
            throw new RuntimeException('The approval request is not linked to an attendance correction.');
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
                'attendance_correction_id' => $approvalRequest->approvable_id,
                'synchronized_by_service' => true,
            ],
        ]);
    }

    private function lockCorrection(AttendanceCorrection $correction): AttendanceCorrection
    {
        return AttendanceCorrection::query()
            ->with([
                'company',
                'employee',
                'attendanceSummary',
                'requestedWorkLocation',
                'approvedWorkLocation',
                'approvalRequest.logs.actor',
                'approvalRequest.steps.approver',
                'approvalRequest.steps.workflowStep',
                'approvalRequest.workflow.steps',
                'submittedBy',
                'approvedBy',
                'rejectedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ])
            ->whereKey($correction->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function freshCorrection(AttendanceCorrection $correction): AttendanceCorrection
    {
        return $correction->fresh([
            'company',
            'employee',
            'attendanceSummary',
            'requestedWorkLocation',
            'approvedWorkLocation',
            'approvalRequest.logs.actor',
            'approvalRequest.steps.approver',
            'approvalRequest.steps.workflowStep',
            'approvalRequest.workflow.steps',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'cancelledBy',
            'createdBy',
            'updatedBy',
        ]);
    }

    private function buildSummary(AttendanceCorrection $correction): string
    {
        $correction->loadMissing('employee');

        return sprintf(
            '%s requested an attendance correction for %s (%s).',
            $correction->employee?->full_name ?? 'Unknown employee',
            $correction->attendance_date->toDateString(),
            AttendanceCorrection::correctionTypeLabels()[$correction->correction_type] ?? $correction->correction_type,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(AttendanceCorrection $correction): array
    {
        return [
            'attendance_correction_id' => $correction->getKey(),
            'attendance_date' => $correction->attendance_date->toDateString(),
            'correction_type' => $correction->correction_type,
            'reason' => $correction->reason,
            'requested_clock_in_at' => $correction->requested_clock_in_at?->toIso8601String(),
            'requested_clock_out_at' => $correction->requested_clock_out_at?->toIso8601String(),
            'requested_work_location_id' => $correction->requested_work_location_id,
            'requested_notes' => $correction->requested_notes,
        ];
    }

    private function assertCompanyScopedActor(AttendanceCorrection $correction, Employee $actor): void
    {
        if ((int) $actor->getEffectiveCompanyId() !== (int) $correction->company_id) {
            throw ValidationException::withMessages([
                'company_id' => 'Attendance corrections cannot be managed across company boundaries.',
            ]);
        }
    }

    private function assertOwnerOrHrManager(AttendanceCorrection $correction, Employee $actor, string $message): void
    {
        if ((int) $correction->employee_id === (int) $actor->getKey()) {
            return;
        }

        if ($actor->canManageHrMasterData() && $actor->canAccessCompany($correction->company_id)) {
            return;
        }

        throw ValidationException::withMessages([
            'employee_id' => $message,
        ]);
    }

    private function assertCanFinalizeDecision(AttendanceCorrection $correction, Employee $actor, string $decision): void
    {
        $approvalRequest = $correction->approvalRequest;

        if (
            $approvalRequest instanceof ApprovalRequest
            && in_array($approvalRequest->status, [ApprovalRequestStatus::APPROVED, ApprovalRequestStatus::REJECTED], true)
        ) {
            return;
        }

        if ($actor->canManageHrMasterData() && $actor->canAccessCompany($correction->company_id)) {
            return;
        }

        $canAct = $decision === 'approve'
            ? ($approvalRequest instanceof ApprovalRequest && $this->approvalActionService->canApprove($approvalRequest, $actor))
            : ($approvalRequest instanceof ApprovalRequest && $this->approvalActionService->canReject($approvalRequest, $actor));

        if ($canAct) {
            return;
        }

        throw ValidationException::withMessages([
            'approval_request_id' => 'You are not allowed to process this attendance correction.',
        ]);
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

    private function requireString(array $payload, string $field): string
    {
        $value = trim((string) ($payload[$field] ?? ''));

        if ($value === '') {
            throw ValidationException::withMessages([
                $field => 'This field is required.',
            ]);
        }

        return $value;
    }

    private function resolveOptionalInteger(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    private function resolveOptionalString(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }

    private function resolveApprovedDateTime(array $payload, string $key, mixed $fallback): ?Carbon
    {
        if (! array_key_exists($key, $payload)) {
            return $fallback instanceof Carbon ? $fallback->copy() : $fallback;
        }

        return $this->parseOptionalDateTime($payload[$key]);
    }

    private function resolveApprovedInteger(array $payload, string $key, mixed $fallback): ?int
    {
        if (! array_key_exists($key, $payload)) {
            return filled($fallback) ? (int) $fallback : null;
        }

        return $this->resolveOptionalInteger($payload[$key]);
    }

    private function resolveApprovedString(array $payload, string $key, mixed $fallback): ?string
    {
        if (! array_key_exists($key, $payload)) {
            return filled($fallback) ? (string) $fallback : null;
        }

        return $this->resolveOptionalString($payload[$key]);
    }
}
