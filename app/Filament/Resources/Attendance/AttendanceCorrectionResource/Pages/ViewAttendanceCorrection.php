<?php

namespace App\Filament\Resources\Attendance\AttendanceCorrectionResource\Pages;

use App\Filament\Resources\Attendance\AttendanceCorrectionResource;
use App\Filament\Resources\Attendance\AttendanceCorrectionResource\Schemas\AttendanceCorrectionInfolist;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCorrectionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ViewAttendanceCorrection extends ViewRecord
{
    protected static string $resource = AttendanceCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('approve', $this->record))
                ->schema([
                    DateTimePicker::make('approved_clock_in_at')->label('Approved Clock In')->seconds(false),
                    DateTimePicker::make('approved_clock_out_at')->label('Approved Clock Out')->seconds(false),
                    Select::make('approved_work_location_id')
                        ->label('Approved Work Location')
                        ->options(fn () => AttendanceCorrectionResource::workLocationOptions())
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Textarea::make('approved_notes')->rows(3),
                    Textarea::make('comments')->label('Approval Notes')->rows(3),
                ])
                ->action(function (array $data): void {
                    $payload = [
                        'approved_clock_in_at' => $data['approved_clock_in_at'] ?? null,
                        'approved_clock_out_at' => $data['approved_clock_out_at'] ?? null,
                        'approved_work_location_id' => $data['approved_work_location_id'] ?? null,
                        'approved_notes' => $data['approved_notes'] ?? null,
                    ];

                    if ($this->record->approvalRequest) {
                        app(AttendanceCorrectionService::class)->processApproval(
                            $this->record->approvalRequest,
                            Auth::user(),
                            'approved',
                            $data['comments'] ?? null,
                            $payload,
                        );
                    } else {
                        app(AttendanceCorrectionService::class)->approve($this->record, Auth::user(), $payload);
                    }

                    $this->record->refresh();

                    Notification::make()->title('Attendance correction approved.')->success()->send();
                }),
            Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('reject', $this->record))
                ->schema([
                    Textarea::make('comments')->label('Rejection Notes')->required()->rows(3),
                ])
                ->action(function (array $data): void {
                    if ($this->record->approvalRequest) {
                        app(AttendanceCorrectionService::class)->processApproval(
                            $this->record->approvalRequest,
                            Auth::user(),
                            'rejected',
                            $data['comments'] ?? null,
                        );
                    } else {
                        app(AttendanceCorrectionService::class)->reject($this->record, Auth::user(), $data['comments'] ?? null);
                    }

                    $this->record->refresh();

                    Notification::make()->title('Attendance correction rejected.')->success()->send();
                }),
            Action::make('cancel')
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->visible(fn (): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('cancel', $this->record))
                ->requiresConfirmation()
                ->action(function (): void {
                    app(AttendanceCorrectionService::class)->cancel($this->record, Auth::user());
                    $this->record->refresh();

                    Notification::make()->title('Attendance correction cancelled.')->success()->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return AttendanceCorrectionInfolist::configure($schema);
    }

    protected function resolveRecord(int|string $key): AttendanceCorrection
    {
        return parent::resolveRecord($key)->load([
            'company',
            'employee',
            'attendanceSummary',
            'requestedWorkLocation',
            'approvedWorkLocation',
            'approvalRequest.workflow.steps',
            'approvalRequest.steps.approver',
            'approvalRequest.steps.workflowStep',
            'approvalRequest.logs.actor',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'cancelledBy',
        ]);
    }
}
