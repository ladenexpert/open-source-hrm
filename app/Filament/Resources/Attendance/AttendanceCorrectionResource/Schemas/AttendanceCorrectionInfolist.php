<?php

namespace App\Filament\Resources\Attendance\AttendanceCorrectionResource\Schemas;

use App\Models\ApprovalLog;
use App\Models\AttendanceCorrection;
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
                            ->formatStateUsing(fn (string $state): string => AttendanceCorrection::statusLabels()[$state] ?? $state),
                        TextEntry::make('attendanceSummary.status')
                            ->label('Linked Summary Status')
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

                                return $step?->workflowStep?->name ?? 'Step '.$approvalRequest->current_step_order;
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

                                return $log?->actor?->full_name;
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
                                TextEntry::make('actor.full_name')->label('Actor')->placeholder('-'),
                                TextEntry::make('comments')->placeholder('-'),
                            ]),
                        ]),
                ]),
        ]);
    }
}
