<?php

namespace App\Filament\Resources\Attendance;

use App\Filament\Resources\Attendance\AttendanceLogResource\Pages\ListAttendanceLogs;
use App\Filament\Resources\Attendance\AttendanceLogResource\Pages\ViewAttendanceLog;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AttendanceLogResource extends Resource
{
    protected static ?string $model = AttendanceLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Attendance Log';

    protected static ?string $pluralModelLabel = 'Attendance Logs';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Company')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('attendance_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceLog::eventTypeLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === AttendanceLog::EVENT_CLOCK_IN ? 'success' : 'warning'),
                TextColumn::make('clocked_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceLog::sourceLabels()[$state] ?? $state),
                TextColumn::make('workLocation.name')
                    ->label('Work Location')
                    ->toggleable(),
                TextColumn::make('is_valid')
                    ->label('Valid')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Valid' : 'Invalid')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('validation_message')
                    ->label('Validation')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->options(static::employeeOptions()),
                Filter::make('attendance_date')
                    ->schema([
                        DatePicker::make('date')->label('Attendance Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['date'] ?? null)) {
                            return $query;
                        }

                        return $query->whereDate('attendance_date', $data['date']);
                    }),
                SelectFilter::make('event_type')
                    ->options(AttendanceLog::eventTypeLabels()),
                SelectFilter::make('source')
                    ->options(AttendanceLog::sourceLabels()),
                SelectFilter::make('is_valid')
                    ->label('Validity')
                    ->options([
                        '1' => 'Valid',
                        '0' => 'Invalid',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (AttendanceLog $record): string => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'company',
            'employee',
            'workLocation',
            'shiftPattern',
            'createdBy',
            'attendanceSelfie',
        ]);

        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->forCompanies($user->accessibleCompanyIds());
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && Gate::forUser(Auth::user())->allows('viewAny', AttendanceLog::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceLogs::route('/'),
            'view' => ViewAttendanceLog::route('/{record}'),
        ];
    }

    private static function companyOptions(): array
    {
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
        }

        return Company::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    private static function employeeOptions(): array
    {
        $query = Employee::query()->orderBy('full_name');
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            $query->whereIn('company_id', $user->accessibleCompanyIds());
        }

        return $query->pluck('full_name', 'id')->all();
    }
}
