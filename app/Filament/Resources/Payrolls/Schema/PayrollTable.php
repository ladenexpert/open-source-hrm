<?php

namespace App\Filament\Resources\Payrolls\Schema;

use App\Models\Employee;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('employee.employee_code')
                    ->label('Emp code')
                    ->sortable()
                    ->searchable(isIndividual: true)
                    ->tooltip('Employee code')
                    ->searchable(),

                TextColumn::make('employee.name')
                    ->label('Name')
                    ->searchable([
                        'first_name',
                        'last_name',
                    ])
                    ->sortable(
                        [
                            'first_name',
                            'last_name',
                        ]
                    ),
                TextColumn::make('pay_date')
                    ->date()
                    ->label('Pay Date')
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Period')
                    ->searchable()
                    ->limit(10)
                    ->sortable(),
                TextColumn::make('gross_pay')
                    ->label('Gross Pay')
                    ->sortable()
                    ->money('KSH', true),
                TextColumn::make('net_pay')
                    ->label('Net Pay')
                    ->sortable()
                    ->money('KSH', true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    })
                    ->label('Status'),

            ])
            ->filters([
                //
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->label('Status'),
                Filter::make('employee')

                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->options(function () {
                                return Employee::all()->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(),

                    ]),

            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
