<?php

namespace App\Filament\Employee\Resources\AttendanceSummaries;

use App\Filament\Employee\Resources\AttendanceSummaries\Pages\ListAttendanceSummaries;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AttendanceSummaryResource extends Resource
{
    protected static ?string $model = AttendanceSummary::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Work space';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'My Attendance Summary';

    protected static ?string $pluralModelLabel = 'My Attendance Summaries';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceSummary::statusLabels()[$state] ?? $state),
                TextColumn::make('actual_in_at')->dateTime()->toggleable(),
                TextColumn::make('actual_out_at')->dateTime()->toggleable(),
                TextColumn::make('late_minutes')->sortable(),
                TextColumn::make('early_out_minutes')->sortable(),
                TextColumn::make('work_minutes')->sortable(),
                TextColumn::make('is_complete')
                    ->label('Complete')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->latest('attendance_date');
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forEmployee($user);
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && Auth::user()->is_active;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceSummaries::route('/'),
        ];
    }
}
