<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Models\Department;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                Department::query()->with('manager')->latest()

            )
            ->columns([
                //
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->limit(10)
                    ->label('Department Code'),
                TextColumn::make('description')
                    ->limit(50)
                    ->label('Description'),
                TextColumn::make('manager_id')
                    ->formatStateUsing(fn ($record) => $record->manager?->name ?? 'No Manager')
                    ->label('Manager')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
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
