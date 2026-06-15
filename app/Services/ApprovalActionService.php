<?php

namespace App\Services;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\Employee;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalActionService
{
    public function approveCurrentStep(ApprovalRequest $request, Employee $actor, ?string $comments = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $comments): ApprovalRequest {
            $request = $this->lockRequest($request);
            $step = $this->resolveCurrentActorStep($request, $actor);

            $step->forceFill([
                'status' => ApprovalStepStatus::APPROVED,
                'acted_at' => now(),
                'comments' => $comments,
            ])->save();

            $request->logs()->create([
                'actor_id' => $actor->getKey(),
                'action' => 'approved',
                'comments' => $comments,
                'metadata' => [
                    'approval_request_step_id' => $step->getKey(),
                    'step_order' => $step->step_order,
                ],
            ]);

            return $this->advanceRequestState($request);
        });
    }

    public function rejectCurrentStep(ApprovalRequest $request, Employee $actor, ?string $comments = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $comments): ApprovalRequest {
            $request = $this->lockRequest($request);
            $step = $this->resolveCurrentActorStep($request, $actor);

            if (! ($step->workflowStep?->can_reject ?? true)) {
                throw new RuntimeException('This approval step does not allow rejection.');
            }

            $step->forceFill([
                'status' => ApprovalStepStatus::REJECTED,
                'acted_at' => now(),
                'comments' => $comments,
            ])->save();

            $request->forceFill([
                'status' => ApprovalRequestStatus::REJECTED,
                'completed_at' => now(),
                'current_step_order' => null,
            ])->save();

            $request->logs()->create([
                'actor_id' => $actor->getKey(),
                'action' => 'rejected',
                'comments' => $comments,
                'metadata' => [
                    'approval_request_step_id' => $step->getKey(),
                    'step_order' => $step->step_order,
                ],
            ]);

            return $request->load($this->eagerLoads());
        });
    }

    public function returnCurrentStep(ApprovalRequest $request, Employee $actor, ?string $comments = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $comments): ApprovalRequest {
            $request = $this->lockRequest($request);
            $step = $this->resolveCurrentActorStep($request, $actor);

            if (! ($step->workflowStep?->can_return ?? false)) {
                throw new RuntimeException('This approval step does not allow return.');
            }

            $step->forceFill([
                'status' => ApprovalStepStatus::RETURNED,
                'acted_at' => now(),
                'comments' => $comments,
            ])->save();

            $request->forceFill([
                'status' => ApprovalRequestStatus::DRAFT,
                'current_step_order' => null,
                'completed_at' => null,
            ])->save();

            $request->logs()->create([
                'actor_id' => $actor->getKey(),
                'action' => 'returned',
                'comments' => $comments,
                'metadata' => [
                    'approval_request_step_id' => $step->getKey(),
                    'step_order' => $step->step_order,
                ],
            ]);

            return $request->load($this->eagerLoads());
        });
    }

    public function cancelRequest(ApprovalRequest $request, Employee $actor, ?string $comments = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $comments): ApprovalRequest {
            $request = $this->lockRequest($request);

            if (! $this->canCancel($request, $actor)) {
                throw new AuthorizationException('You are not allowed to cancel this approval request.');
            }

            $request->steps()
                ->where('status', ApprovalStepStatus::PENDING)
                ->update([
                    'status' => ApprovalStepStatus::SKIPPED,
                    'comments' => 'Request cancelled.',
                    'updated_at' => now(),
                ]);

            $request->forceFill([
                'status' => ApprovalRequestStatus::CANCELLED,
                'completed_at' => now(),
                'current_step_order' => null,
            ])->save();

            $request->logs()->create([
                'actor_id' => $actor->getKey(),
                'action' => 'cancelled',
                'comments' => $comments,
            ]);

            return $request->load($this->eagerLoads());
        });
    }

    public function canApprove(ApprovalRequest $request, Employee $actor): bool
    {
        return $this->canAct($request, $actor);
    }

    public function canReject(ApprovalRequest $request, Employee $actor): bool
    {
        return $this->canAct($request, $actor);
    }

    public function canCancel(ApprovalRequest $request, Employee $actor): bool
    {
        if (! in_array($request->status, [ApprovalRequestStatus::DRAFT, ApprovalRequestStatus::PENDING], true)) {
            return false;
        }

        if ((int) $request->requester_id === (int) $actor->getKey()) {
            return true;
        }

        return $actor->canManageHrMasterData()
            && (
                $actor->canAccessCompany($request->company_id)
                || (filled($request->company_group_id) && $actor->canAccessCompanyGroup($request->company_group_id))
            );
    }

    private function canAct(ApprovalRequest $request, Employee $actor): bool
    {
        if ($request->status !== ApprovalRequestStatus::PENDING) {
            return false;
        }

        if ((int) $request->requester_id === (int) $actor->getKey()) {
            return false;
        }

        return $request->isPendingForApprover($actor)
            && (
                $actor->canAccessCompany($request->company_id)
                || (filled($request->company_group_id) && $actor->canAccessCompanyGroup($request->company_group_id))
            );
    }

    private function resolveCurrentActorStep(ApprovalRequest $request, Employee $actor): ApprovalRequestStep
    {
        if (! $this->canAct($request, $actor)) {
            throw new AuthorizationException('You are not allowed to act on this approval request.');
        }

        $step = $request->steps()
            ->with('workflowStep')
            ->where('step_order', $request->current_step_order)
            ->where('approver_id', $actor->getKey())
            ->where('status', ApprovalStepStatus::PENDING)
            ->first();

        if (! $step instanceof ApprovalRequestStep) {
            throw new RuntimeException('No pending approval step is assigned to the current approver.');
        }

        return $step;
    }

    private function advanceRequestState(ApprovalRequest $request): ApprovalRequest
    {
        $request->refresh();

        $hasPendingCurrentSteps = $request->steps()
            ->where('step_order', $request->current_step_order)
            ->where('status', ApprovalStepStatus::PENDING)
            ->exists();

        if ($hasPendingCurrentSteps) {
            return $request->load($this->eagerLoads());
        }

        $nextStepOrder = $request->steps()
            ->where('status', ApprovalStepStatus::PENDING)
            ->min('step_order');

        if (filled($nextStepOrder)) {
            $request->forceFill([
                'current_step_order' => (int) $nextStepOrder,
            ])->save();

            return $request->load($this->eagerLoads());
        }

        $request->forceFill([
            'status' => ApprovalRequestStatus::APPROVED,
            'current_step_order' => null,
            'completed_at' => now(),
        ])->save();

        $request->logs()->create([
            'action' => 'fully_approved',
            'metadata' => [
                'completed_at' => now()->toIso8601String(),
            ],
        ]);

        return $request->load($this->eagerLoads());
    }

    private function lockRequest(ApprovalRequest $request): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->whereKey($request->getKey())
            ->lockForUpdate()
            ->firstOrFail()
            ->load($this->eagerLoads());
    }

    private function eagerLoads(): array
    {
        return [
            'workflow.steps',
            'steps.workflowStep',
            'steps.approver',
            'logs.actor',
            'requester',
            'employeeSubject',
        ];
    }
}
