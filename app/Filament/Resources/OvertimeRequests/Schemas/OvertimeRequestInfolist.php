<?php

namespace App\Filament\Resources\OvertimeRequests\Schemas;

use App\Models\ApprovalLog;
use App\Models\OvertimeRequest;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OvertimeRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request Overview')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('company.name')->label('Company'),
                        TextEntry::make('employee.full_name')->label('Employee'),
                        TextEntry::make('overtime_date')->date(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => OvertimeRequest::statusLabels()[$state] ?? $state)
                            ->color(fn (string $state): string => OvertimeRequest::statusColor($state)),
                        TextEntry::make('requested_start_at')->dateTime()->placeholder('-'),
                        TextEntry::make('requested_end_at')->dateTime()->placeholder('-'),
                        TextEntry::make('requested_minutes')->placeholder('-'),
                        TextEntry::make('approved_minutes')->placeholder('-'),
                        TextEntry::make('reason')->columnSpanFull()->placeholder('-'),
                    ]),
                ]),
            Section::make('Attendance Reference')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('attendanceSummary.id')
                            ->label('Attendance Summary')
                            ->placeholder('No summary linked.'),
                        TextEntry::make('attendanceSummary.status')
                            ->label('Attendance Status')
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('attendanceSummary.scheduled_end_at')
                            ->label('Scheduled End')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('attendanceSummary.actual_in_at')
                            ->label('Actual Clock In')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('attendanceSummary.actual_out_at')
                            ->label('Actual Clock Out')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('attendanceSummary.attendancePolicy.overtime_threshold_minutes')
                            ->label('OT Threshold (min)')
                            ->placeholder('-'),
                    ]),
                ]),
            Section::make('Calculation')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('calculation.calculation_status')
                            ->label('Calculation Status')
                            ->badge()
                            ->placeholder('Not calculated.'),
                        TextEntry::make('calculation.calculated_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('calculation.actual_overtime_minutes')
                            ->label('Actual OT Minutes')
                            ->placeholder('-'),
                        TextEntry::make('calculation.calculated_minutes')
                            ->label('Calculated OT Minutes')
                            ->placeholder('-'),
                        TextEntry::make('calculation.requested_minutes')
                            ->label('Requested Minutes Snapshot')
                            ->placeholder('-'),
                        TextEntry::make('calculation.approved_minutes')
                            ->label('Approved Minutes Snapshot')
                            ->placeholder('-'),
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
                            ->state(function (OvertimeRequest $record): string {
                                $approvalRequest = $record->approvalRequest;

                                if (! $approvalRequest?->current_step_order) {
                                    return '-';
                                }

                                $step = $approvalRequest->steps
                                    ->firstWhere('step_order', $approvalRequest->current_step_order);

                                return $step?->workflowStep?->name ?? 'Step '.$approvalRequest->current_step_order;
                            })
                            ->placeholder('-'),
                        TextEntry::make('submittedBy.full_name')
                            ->label('Submitted By')
                            ->placeholder('-'),
                        TextEntry::make('submitted_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('approvedBy.full_name')
                            ->label('Approved By')
                            ->placeholder('-'),
                        TextEntry::make('approved_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('rejectedBy.full_name')
                            ->label('Rejected By')
                            ->placeholder('-'),
                        TextEntry::make('rejected_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('rejection_reason')
                            ->columnSpanFull()
                            ->placeholder('-'),
                        TextEntry::make('cancellation_reason')
                            ->columnSpanFull()
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
                                TextEntry::make('created_at')
                                    ->label('Date')
                                    ->dateTime(),
                                TextEntry::make('action')
                                    ->badge(),
                                TextEntry::make('actor.full_name')
                                    ->label('Actor')
                                    ->placeholder('-'),
                                TextEntry::make('comments')
                                    ->placeholder('-'),
                            ]),
                        ]),
                ]),
        ]);
    }
}
