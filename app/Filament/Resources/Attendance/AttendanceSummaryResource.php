<?php

namespace App\Filament\Resources\Attendance;

use App\Filament\Resources\Attendance\AttendanceSummaryResource\Pages\ListAttendanceSummaries;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCalculationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AttendanceSummaryResource extends Resource
{
    protected static ?string $model = AttendanceSummary::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Attendance Summary';

    protected static ?string $pluralModelLabel = 'Attendance Summaries';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Attendance Summary')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('company.name')->label('Company')->disabled(),
                        TextInput::make('employee.full_name')->label('Employee')->disabled(),
                        TextInput::make('attendance_date')->disabled(),
                        TextInput::make('status')
                            ->formatStateUsing(fn (?string $state): string => AttendanceSummary::statusLabels()[$state] ?? (string) $state)
                            ->disabled(),
                        TextInput::make('scheduled_start_at')->disabled(),
                        TextInput::make('scheduled_end_at')->disabled(),
                        TextInput::make('actual_in_at')->disabled(),
                        TextInput::make('actual_out_at')->disabled(),
                        TextInput::make('break_duration_minutes')->disabled(),
                        TextInput::make('work_minutes')->disabled(),
                        TextInput::make('late_minutes')->disabled(),
                        TextInput::make('early_out_minutes')->disabled(),
                        TextInput::make('is_complete')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Complete' : 'Incomplete')
                            ->disabled(),
                        TextInput::make('calculated_at')->disabled(),
                    ]),
                    Textarea::make('calculation_notes')
                        ->rows(3)
                        ->disabled()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable()->sortable(),
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceSummary::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => AttendanceSummary::statusColor($state)),
                TextColumn::make('scheduled_start_at')->dateTime()->toggleable(),
                TextColumn::make('scheduled_end_at')->dateTime()->toggleable(),
                TextColumn::make('actual_in_at')->dateTime()->toggleable(),
                TextColumn::make('actual_out_at')->dateTime()->toggleable(),
                TextColumn::make('work_minutes')->sortable(),
                TextColumn::make('late_minutes')->sortable(),
                TextColumn::make('early_out_minutes')->sortable(),
                TextColumn::make('is_complete')
                    ->label('Complete')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                TextColumn::make('calculated_at')->dateTime()->toggleable(),
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
                SelectFilter::make('is_complete')
                    ->label('Completion')
                    ->options([
                        '1' => 'Complete',
                        '0' => 'Incomplete',
                    ]),
            ])
            ->recordActions([
                Action::make('recalculate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->authorize(fn (AttendanceSummary $record): bool => Gate::forUser(Auth::user())->allows('recalculate', $record))
                    ->action(function (AttendanceSummary $record, AttendanceCalculationService $attendanceCalculationService): void {
                        $attendanceCalculationService->recalculateSummary($record);

                        Notification::make()
                            ->title('Attendance summary recalculated.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'company',
            'employee',
            'shiftPattern',
            'shiftPatternDetail',
            'attendancePolicy',
            'workLocation',
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
            && Gate::forUser(Auth::user())->allows('viewAny', AttendanceSummary::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceSummaries::route('/'),
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
