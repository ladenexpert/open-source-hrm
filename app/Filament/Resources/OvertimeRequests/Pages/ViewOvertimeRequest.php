<?php

namespace App\Filament\Resources\OvertimeRequests\Pages;

use App\Filament\Resources\OvertimeRequests\OvertimeRequestResource;
use App\Filament\Resources\OvertimeRequests\Schemas\OvertimeRequestInfolist;
use App\Models\ApprovalRequest;
use App\Models\OvertimeRequest;
use App\Services\OvertimeCalculationService;
use App\Services\OvertimeRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewOvertimeRequest extends ViewRecord
{
    protected static string $resource = OvertimeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (): bool => Auth::user()?->can('submit', $this->record) ?? false)
                ->action(function (): void {
                    app(OvertimeRequestService::class)->submit($this->record, Auth::user());
                    $this->record->refresh();

                    Notification::make()
                        ->title('Overtime request submitted.')
                        ->success()
                        ->send();
                }),
            Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => Auth::user()?->can('approve', $this->record) ?? false)
                ->schema([
                    TextInput::make('approved_minutes')
                        ->numeric()
                        ->minValue(0)
                        ->nullable(),
                    Textarea::make('comments')
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    $payload = [
                        'approved_minutes' => $data['approved_minutes'] ?? null,
                    ];

                    if ($this->record->approvalRequest instanceof ApprovalRequest) {
                        app(OvertimeRequestService::class)->processApproval(
                            $this->record->approvalRequest,
                            Auth::user(),
                            'approved',
                            $data['comments'] ?? null,
                            $payload,
                        );
                    } else {
                        app(OvertimeRequestService::class)->approve($this->record, Auth::user(), $payload);
                    }

                    $this->record->refresh();

                    Notification::make()
                        ->title('Overtime request approved.')
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => Auth::user()?->can('reject', $this->record) ?? false)
                ->schema([
                    Textarea::make('comments')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    if ($this->record->approvalRequest instanceof ApprovalRequest) {
                        app(OvertimeRequestService::class)->processApproval(
                            $this->record->approvalRequest,
                            Auth::user(),
                            'rejected',
                            $data['comments'] ?? null,
                        );
                    } else {
                        app(OvertimeRequestService::class)->reject($this->record, Auth::user(), $data['comments'] ?? null);
                    }

                    $this->record->refresh();

                    Notification::make()
                        ->title('Overtime request rejected.')
                        ->success()
                        ->send();
                }),
            Action::make('cancel')
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->visible(fn (): bool => Auth::user()?->can('cancel', $this->record) ?? false)
                ->schema([
                    Textarea::make('reason')
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(OvertimeRequestService::class)->cancel($this->record, Auth::user(), $data['reason'] ?? null);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Overtime request cancelled.')
                        ->success()
                        ->send();
                }),
            Action::make('calculate')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->visible(fn (): bool => Auth::user()?->can('calculate', $this->record) ?? false)
                ->action(function (): void {
                    app(OvertimeCalculationService::class)->calculateForRequest($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Overtime calculation refreshed.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return OvertimeRequestInfolist::configure($schema);
    }

    protected function resolveRecord(int|string $key): OvertimeRequest
    {
        return parent::resolveRecord($key)->load([
            'company',
            'employee',
            'attendanceSummary.attendancePolicy',
            'attendanceSummary.firstLog',
            'attendanceSummary.lastLog',
            'approvalRequest.workflow.steps',
            'approvalRequest.steps.approver',
            'approvalRequest.steps.workflowStep',
            'approvalRequest.logs.actor',
            'calculation.attendanceSummary',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'cancelledBy',
            'createdBy',
            'updatedBy',
        ]);
    }
}
