<?php

namespace App\Filament\Resources\ApprovalRequests\Pages;

use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Filament\Resources\ApprovalRequests\Schemas\ApprovalRequestInfolist;
use App\Models\ApprovalRequest;
use App\Services\ApprovalActionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ViewApprovalRequest extends ViewRecord
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->schema([
                    Textarea::make('comments')
                        ->rows(4),
                ])
                ->visible(fn (): bool => Gate::forUser(Auth::user())->allows('approve', $this->record))
                ->action(function (array $data): void {
                    app(ApprovalActionService::class)->approveCurrentStep($this->record, Auth::user(), $data['comments'] ?? null);
                    $this->record->refresh();

                    Notification::make()->title('Approval step completed.')->success()->send();
                }),
            Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->schema([
                    Textarea::make('comments')
                        ->required()
                        ->rows(4),
                ])
                ->visible(fn (): bool => Gate::forUser(Auth::user())->allows('reject', $this->record))
                ->action(function (array $data): void {
                    app(ApprovalActionService::class)->rejectCurrentStep($this->record, Auth::user(), $data['comments'] ?? null);
                    $this->record->refresh();

                    Notification::make()->title('Approval request rejected.')->success()->send();
                }),
            Action::make('cancel')
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->schema([
                    Textarea::make('comments')
                        ->rows(4),
                ])
                ->visible(fn (): bool => Gate::forUser(Auth::user())->allows('cancel', $this->record))
                ->action(function (array $data): void {
                    app(ApprovalActionService::class)->cancelRequest($this->record, Auth::user(), $data['comments'] ?? null);
                    $this->record->refresh();

                    Notification::make()->title('Approval request cancelled.')->success()->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return ApprovalRequestInfolist::configure($schema);
    }

    protected function resolveRecord(int|string $key): ApprovalRequest
    {
        return parent::resolveRecord($key)->load([
            'company',
            'workflow.steps',
            'requester',
            'employeeSubject',
            'steps.workflowStep',
            'steps.approver',
            'logs.actor',
        ]);
    }
}
