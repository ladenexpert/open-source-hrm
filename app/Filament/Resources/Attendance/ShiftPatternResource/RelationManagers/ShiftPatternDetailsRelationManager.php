<?php

namespace App\Filament\Resources\Attendance\ShiftPatternResource\RelationManagers;

use App\Models\ShiftPatternDetail;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftPatternDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'details';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('day_of_week')
                    ->options(ShiftPatternDetail::dayOptions())
                    ->required(),
                Toggle::make('is_working_day')
                    ->default(true)
                    ->live(),
                TextInput::make('start_time')
                    ->required(fn ($get): bool => (bool) $get('is_working_day'))
                    ->disabled(fn ($get): bool => ! (bool) $get('is_working_day'))
                    ->type('time'),
                TextInput::make('end_time')
                    ->required(fn ($get): bool => (bool) $get('is_working_day'))
                    ->disabled(fn ($get): bool => ! (bool) $get('is_working_day'))
                    ->type('time'),
                TextInput::make('break_duration_minutes')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_of_week')
                    ->formatStateUsing(fn (int $state): string => ShiftPatternDetail::dayOptions()[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('start_time'),
                TextColumn::make('end_time'),
                TextColumn::make('break_duration_minutes')->label('Break (mins)'),
                IconColumn::make('is_working_day')->label('Working Day')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
