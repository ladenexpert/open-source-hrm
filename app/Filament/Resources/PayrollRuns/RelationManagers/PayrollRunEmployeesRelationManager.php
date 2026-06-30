<?php

namespace App\Filament\Resources\PayrollRuns\RelationManagers;

use App\Models\PayrollRunEmployee;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollRunEmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'payrollRunEmployees';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('employee.full_name')
            ->columns([
                TextColumn::make('employee.full_name')->label('Employee')->searchable()->sortable(),
                TextColumn::make('snapshot_status')
                    ->label('Snapshot Status')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PayrollRunEmployee::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => PayrollRunEmployee::statusColor($state)),
                TextColumn::make('readiness_message')
                    ->label('Readiness')
                    ->wrap()
                    ->placeholder('-'),
                TextColumn::make('total_work_days')->label('Work Days')->sortable(),
                TextColumn::make('total_present_days')->label('Present')->sortable(),
                TextColumn::make('total_absent_days')->label('Absent')->sortable(),
                TextColumn::make('total_late_minutes')->label('Late')->sortable(),
                TextColumn::make('total_overtime_minutes')->label('OT')->sortable(),
                TextColumn::make('total_leave_days')->label('Leave')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PayrollRunEmployee::statusLabels()),
                SelectFilter::make('snapshot_status')
                    ->options([
                        'draft' => 'Draft',
                        'calculated' => 'Calculated',
                        'locked' => 'Locked',
                        'stale' => 'Stale',
                        'cancelled' => 'Cancelled',
                    ]),
            ]);
    }
}
