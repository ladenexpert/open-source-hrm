<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EmployeeTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                Employee::query()
                    ->with(['department'])
                    ->latest()
            )
            ->filters(
                [

                    Filter::make('is_active')
                        ->label('Active Employees')
                        // ->toggle()
                        ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                        ->default(false),
                    Filter::make('is_inactive')
                        ->label('Inactive Employees')
                        // ->toggle()
                        ->query(fn (Builder $query): Builder => $query->where('is_active', false))
                        ->default(false),
                    SelectFilter::make('department_id')
                        ->label('Department')
                        ->options(
                            fn () => Department::query()
                                ->when(
                                    Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                                    fn (Builder $query): Builder => $query->forCompany(Auth::user()->getEffectiveCompanyId()),
                                )
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                        )
                        ->searchable(),
                    SelectFilter::make('employment_type')
                        ->label('Employment Type')
                        ->options([
                            'Permanent' => 'Permanent',
                            'Contract' => 'Contract',
                            'Casual' => 'Casual',
                        ]),
                    SelectFilter::make('position_id')
                        ->label('Position')
                        ->options(
                            Position::query()
                                ->when(
                                    Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                                    fn (Builder $query): Builder => $query->forCompany(Auth::user()->getEffectiveCompanyId()),
                                )
                                ->orderBy('title')
                                ->pluck('title', 'id')
                                ->all()
                        )
                        ->searchable(),

                ],

            )
            ->columns([
                //
                TextColumn::make('employee_code')
                    ->label('Employee code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(
                        [
                            'first_name',
                            'last_name',
                        ]
                    )
                    ->sortable([
                        'first_name',
                        'last_name',
                    ]),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position.title')
                    ->label('Position')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('national_id')
                    ->label('National ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('kra_pin')
                    ->label('KRA PIN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('employment_type')
                    ->label('Employment Type')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Is Active')

                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(),
                TextColumn::make('date_of_birth')
                    ->label('Date of Birth')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('termination_date')
                    ->label('Termination Date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('hire_date')
                    ->label('Hire Date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

            ])

            ->recordActions([
                ActionGroup::make([

                    EditAction::make(),
                    ViewAction::make(),
                    DeleteAction::make(),
                ]),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
