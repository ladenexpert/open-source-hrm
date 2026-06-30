<?php

namespace App\Filament\Resources\PayrollRuns\Schemas;

use App\Models\PayrollRun;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Run Overview')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('company.name')->label('Company'),
                        TextEntry::make('payrollPeriod.name')->label('Payroll Period'),
                        TextEntry::make('run_code')->placeholder('-'),
                        TextEntry::make('run_type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => PayrollRun::runTypeLabels()[$state] ?? $state),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => PayrollRun::statusLabels()[$state] ?? $state),
                        TextEntry::make('payrollPeriod.pay_date')->label('Pay Date')->date()->placeholder('-'),
                        TextEntry::make('period_start')->date(),
                        TextEntry::make('period_end')->date(),
                        TextEntry::make('prepared_at')->dateTime()->placeholder('-'),
                        TextEntry::make('locked_at')->dateTime()->placeholder('-'),
                        TextEntry::make('lockedBy.full_name')->label('Locked By')->placeholder('-'),
                        TextEntry::make('approved_at')->dateTime()->placeholder('-'),
                        TextEntry::make('approvedBy.full_name')->label('Approved By')->placeholder('-'),
                        TextEntry::make('cancelled_at')->dateTime()->placeholder('-'),
                        TextEntry::make('cancelledBy.full_name')->label('Cancelled By')->placeholder('-'),
                        TextEntry::make('cancellation_reason')->columnSpanFull()->placeholder('-'),
                    ]),
                ]),
            Section::make('Readiness Counts')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('total_employees')->label('Employees'),
                        TextEntry::make('ready_employees')->label('Ready'),
                        TextEntry::make('blocked_employees')->label('Blocked'),
                    ]),
                ]),
        ]);
    }
}
