<?php

namespace App\Filament\Resources\Attendance\AttendanceCorrectionResource\Schemas;

use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflowStep;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceCorrectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Correction Overview')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('company.name')->label('Company'),
                        TextEntry::make('employee.full_name')->label('Employee'),
                        TextEntry::make('attendance_date')->date(),
                        TextEntry::make('correction_type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => AttendanceCorrection::correctionTypeLabels()[$state] ?? $state),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => AttendanceCorrection::statusLabels()[$state] ?? $state)
                            ->color(fn (string $state): string => AttendanceCorrection::statusColor($state)),
                        TextEntry::make('attendanceSummary.status')
                            ->label('Linked Summary Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => AttendanceSummary::statusLabels()[$state] ?? ($state ?: '-'))
                            ->color(fn (?string $state): string => AttendanceSummary::statusColor($state))
                            ->placeholder('-'),
                        TextEntry::make('requested_clock_in_at')->dateTime()->placeholder('-'),
                        TextEntry::make('requested_clock_out_at')->dateTime()->placeholder('-'),
                        TextEntry::make('approved_clock_in_at')->dateTime()->placeholder('-'),
                        TextEntry::make('approved_clock_out_at')->dateTime()->placeholder('-'),
                        TextEntry::make('requestedWorkLocation.name')->label('Requested Work Location')->placeholder('-'),
                        TextEntry::make('approvedWorkLocation.name')->label('Approved Work Location')->placeholder('-'),
                        TextEntry::make('reason')->columnSpanFull(),
                        TextEntry::make('requested_notes')->columnSpanFull()->placeholder('-'),
                        TextEntry::make('approved_notes')->columnSpanFull()->placeholder('-'),
                    ]),
                ]),
            Section::make('Timeline')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('created_at')->dateTime()->label('Created At'),
                        TextEntry::make('submitted_at')->dateTime()->placeholder('-'),
                        TextEntry::make('submittedBy.full_name')->label('Submitted By')->placeholder('-'),
                        TextEntry::make('approved_at')->dateTime()->placeholder('-'),
                        TextEntry::make('approvedBy.full_name')->label('Approved By')->placeholder('-'),
                        TextEntry::make('rejected_at')->dateTime()->placeholder('-'),
                        TextEntry::make('rejectedBy.full_name')->label('Rejected By')->placeholder('-'),
                        TextEntry::make('cancelled_at')->dateTime()->placeholder('-'),
                        TextEntry::make('cancelledBy.full_name')->label('Cancelled By')->placeholder('-'),
                    ]),
                ]),
            Section::make('Approval Status')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('approvalRequest.status')
                            ->label('Approval Status')
                            ->badge()
                            ->placeholder('No approval request.'),
                        TextEntry::make('approval_current_step')
                            ->label('Current Approval Step')
                            ->state(function (AttendanceCorrection $record): string {
                                $approvalRequest = $record->approvalRequest;

                                if (! $approvalRequest?->current_step_order) {
                                    return '-';
                                }

                                $step = $approvalRequest->steps
                                    ->firstWhere('step_order', $approvalRequest->current_step_order);

                                if (! $step) {
                                    return 'Step '.$approvalRequest->current_step_order;
                                }

                                if ($step->relationLoaded('workflowStep')) {
                                    return $step->workflowStep?->name ?? 'Step '.$approvalRequest->current_step_order;
                                }

                                if (filled($step->approval_workflow_step_id)) {
                                    return ApprovalWorkflowStep::query()
                                        ->whereKey($step->approval_workflow_step_id)
                                        ->value('name') ?? 'Step '.$approvalRequest->current_step_order;
                                }

                                return 'Step '.$approvalRequest->current_step_order;
                            })
                            ->placeholder('-'),
                        TextEntry::make('approvalRequest.submitted_at')
                            ->label('Approval Submitted')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('approval_last_actor')
                            ->label('Latest Approver')
                            ->state(function (AttendanceCorrection $record): ?string {
                                $log = $record->approvalRequest?->logs
                                    ?->filter(fn (ApprovalLog $log): bool => in_array($log->action, ['approved', 'rejected', 'fully_approved'], true))
                                    ->last();

                                if (! $log) {
                                    return null;
                                }

                                if ($log->relationLoaded('actor')) {
                                    return $log->actor?->full_name;
                                }

                                if (filled($log->actor_id)) {
                                    return Employee::query()->whereKey($log->actor_id)->value('full_name');
                                }

                                return null;
                            })
                            ->placeholder('-'),
                    ]),
                ]),
            Section::make('Approval Timeline')
                ->schema([
                    RepeatableEntry::make('approvalRequest.logs')
                        ->label('')
                        ->placeholder('No approval history yet.')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('created_at')->label('Date')->dateTime(),
                                TextEntry::make('action')->badge(),
                                TextEntry::make('actor_name')
                                    ->label('Actor')
                                    ->state(function (ApprovalLog $record): ?string {
                                        if ($record->relationLoaded('actor')) {
                                            return $record->actor?->full_name;
                                        }

                                        if (filled($record->actor_id)) {
                                            return Employee::query()->whereKey($record->actor_id)->value('full_name');
                                        }

                                        return null;
                                    })
                                    ->placeholder('-'),
                                TextEntry::make('comments')->placeholder('-'),
                            ]),
                        ]),
                ]),
        ]);
    }
}
