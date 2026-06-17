<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveRequests\Schemas\LeaveRequestInfolist;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\ApprovalActionService;
use App\Services\Leave\LeaveApprovalService;
use App\Services\LeaveRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewLeaveRequest extends ViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(function (): bool {
                    $user = Auth::user();
                    $approvalRequest = $this->record->approvalRequest()->first();

                    return $user instanceof Employee
                        && $approvalRequest !== null
                        && app(ApprovalActionService::class)->canApprove($approvalRequest, $user);
                })
                ->schema([
                    Textarea::make('comments')
                        ->label('Notes')
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    $approvalRequest = $this->record->approvalRequest()->firstOrFail();

                    app(LeaveApprovalService::class)->processApproval(
                        $approvalRequest,
                        Auth::user(),
                        'approved',
                        $data['comments'] ?? null,
                    );

                    Notification::make()
                        ->title('Approval step completed.')
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(function (): bool {
                    $user = Auth::user();
                    $approvalRequest = $this->record->approvalRequest()->first();

                    return $user instanceof Employee
                        && $approvalRequest !== null
                        && app(ApprovalActionService::class)->canReject($approvalRequest, $user);
                })
                ->schema([
                    Textarea::make('reason')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    $approvalRequest = $this->record->approvalRequest()->firstOrFail();

                    app(LeaveApprovalService::class)->processApproval(
                        $approvalRequest,
                        Auth::user(),
                        'rejected',
                        $data['reason'] ?? null,
                    );

                    Notification::make()
                        ->title('Leave request rejected.')
                        ->success()
                        ->send();
                }),
            Action::make('cancelApproved')
                ->label('Cancel Approved')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => Auth::user() instanceof Employee && Auth::user()->can('cancelApproved', $this->record))
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(LeaveRequestService::class)->cancelApproved(
                        $this->record,
                        Auth::user(),
                        $data['reason'] ?? null,
                    );

                    Notification::make()
                        ->title('Approved leave request cancelled.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return LeaveRequestInfolist::configure($schema);
    }

    protected function resolveRecord(int|string $key): LeaveRequest
    {
        return parent::resolveRecord($key)->load([
            'company',
            'employee',
            'leaveType',
            'leaveEntitlement',
            'attachment',
            'cancelledBy',
            'approvalRequest.workflow.steps',
            'approvalRequest.steps.approver',
            'approvalRequest.steps.workflowStep',
            'approvalRequest.logs.actor',
        ]);
    }
}
