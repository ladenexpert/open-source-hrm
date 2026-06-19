<?php

namespace App\Filament\Employee\Resources\AttendanceSummaries;

use App\Filament\Employee\Resources\AttendanceCorrections\AttendanceCorrectionResource as PortalAttendanceCorrectionResource;
use App\Filament\Employee\Resources\AttendanceSummaries\Pages\ListAttendanceSummaries;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AttendanceSummaryResource extends Resource
{
    protected static ?string $model = AttendanceSummary::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Work space';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'My Attendance History';

    protected static ?string $pluralModelLabel = 'My Attendance History';

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
                TextColumn::make('shiftPattern.name')
                    ->label('Shift')
                    ->toggleable(),
                TextColumn::make('workLocation.name')
                    ->label('Work Location')
                    ->toggleable(),
                TextColumn::make('is_complete')
                    ->label('Complete')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
            ])
            ->filters([
                Filter::make('attendance_date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['from'] ?? null)) {
                            $query->whereDate('attendance_date', '>=', $data['from']);
                        }

                        if (filled($data['until'] ?? null)) {
                            $query->whereDate('attendance_date', '<=', $data['until']);
                        }

                        return $query;
                    }),
                SelectFilter::make('status')
                    ->options(AttendanceSummary::statusLabels()),
            ])
            ->recordActions([
                Action::make('requestCorrection')
                    ->label('Request Correction')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (AttendanceSummary $record): string => PortalAttendanceCorrectionResource::getCreateUrlForSummary($record)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['shiftPattern', 'workLocation'])
            ->latest('attendance_date');
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->forCompany($user->getEffectiveCompanyId())
            ->forEmployee($user);
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
