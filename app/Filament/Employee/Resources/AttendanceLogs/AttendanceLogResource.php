<?php

namespace App\Filament\Employee\Resources\AttendanceLogs;

use App\Filament\Employee\Resources\AttendanceLogs\Pages\ListAttendanceLogs;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AttendanceLogResource extends Resource
{
    protected static ?string $model = AttendanceLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Work space';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'My Attendance Log';

    protected static ?string $pluralModelLabel = 'My Attendance Logs';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('clocked_at', 'desc')
            ->columns([
                TextColumn::make('clocked_at')
                    ->label('Clocked At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceLog::eventTypeLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === AttendanceLog::EVENT_CLOCK_IN ? 'success' : 'warning'),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceLog::sourceLabels()[$state] ?? $state),
                TextColumn::make('workLocation.name')
                    ->label('Work Location')
                    ->toggleable(),
                TextColumn::make('is_valid')
                    ->label('Validity')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Valid' : 'Invalid')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('validation_message')
                    ->label('Validation')
                    ->limit(60)
                    ->wrap(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('workLocation')
            ->latest('clocked_at');

        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->forCompany($user->getEffectiveCompanyId())
            ->forEmployee($user)
            ->forDate(now(config('app.timezone'))->toDateString());
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && Auth::user()->is_active;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceLogs::route('/'),
        ];
    }
}
