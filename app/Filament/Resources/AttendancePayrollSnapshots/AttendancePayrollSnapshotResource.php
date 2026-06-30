<?php

namespace App\Filament\Resources\AttendancePayrollSnapshots;

use App\Filament\Resources\AttendancePayrollSnapshots\Pages\ListAttendancePayrollSnapshots;
use App\Models\AttendancePayrollSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Services\AttendancePayrollReadinessService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AttendancePayrollSnapshotResource extends Resource
{
    protected static ?string $model = AttendancePayrollSnapshot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Attendance Payroll Snapshot';

    protected static ?string $pluralModelLabel = 'Attendance Payroll Snapshots';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period_start', 'desc')
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable()->sortable(),
                TextColumn::make('period_start')->date()->label('Period Start')->sortable(),
                TextColumn::make('period_end')->date()->label('Period End')->sortable(),
                TextColumn::make('snapshot_status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendancePayrollSnapshot::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => AttendancePayrollSnapshot::statusColor($state)),
                TextColumn::make('total_work_days')->label('Work Days')->sortable(),
                TextColumn::make('total_present_days')->label('Present')->sortable(),
                TextColumn::make('total_absent_days')->label('Absent')->sortable(),
                TextColumn::make('total_late_minutes')->label('Late')->sortable(),
                TextColumn::make('total_early_leave_minutes')->label('Early Leave')->sortable(),
                TextColumn::make('total_work_minutes')->label('Work Minutes')->sortable(),
                TextColumn::make('total_overtime_minutes')->label('OT Minutes')->sortable(),
                TextColumn::make('total_leave_days')
                    ->label('Leave Days')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 2)),
                TextColumn::make('total_correction_count')->label('Corrections')->sortable(),
                TextColumn::make('calculated_at')->dateTime()->toggleable(),
                TextColumn::make('locked_at')->dateTime()->toggleable(),
                TextColumn::make('lockedBy.full_name')->label('Locked By')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->options(static::employeeOptions()),
                SelectFilter::make('snapshot_status')
                    ->label('Status')
                    ->options(AttendancePayrollSnapshot::statusLabels()),
                Filter::make('period')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['from'] ?? null)) {
                            $query->whereDate('period_start', '>=', $data['from']);
                        }

                        if (filled($data['until'] ?? null)) {
                            $query->whereDate('period_end', '<=', $data['until']);
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                Action::make('recalculate')
                    ->label('Recalculate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->authorize(fn (AttendancePayrollSnapshot $record): bool => Gate::forUser(Auth::user())->allows('recalculate', $record))
                    ->visible(fn (AttendancePayrollSnapshot $record): bool => ! $record->isLocked())
                    ->action(function (AttendancePayrollSnapshot $record, AttendancePayrollReadinessService $service): void {
                        $user = Auth::user();

                        $service->recalculateSnapshot($record, $user instanceof Employee ? $user : null);

                        Notification::make()
                            ->title('Attendance payroll snapshot recalculated.')
                            ->success()
                            ->send();
                    }),
                Action::make('lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('primary')
                    ->authorize(fn (AttendancePayrollSnapshot $record): bool => Gate::forUser(Auth::user())->allows('lock', $record))
                    ->visible(fn (AttendancePayrollSnapshot $record): bool => $record->isCalculated())
                    ->requiresConfirmation()
                    ->action(function (AttendancePayrollSnapshot $record, AttendancePayrollReadinessService $service): void {
                        $user = Auth::user();

                        $service->lockSnapshot($record, $user instanceof Employee ? $user : null);

                        Notification::make()
                            ->title('Attendance payroll snapshot locked.')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_stale')
                    ->label('Mark Stale')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->authorize(fn (AttendancePayrollSnapshot $record): bool => Gate::forUser(Auth::user())->allows('markStale', $record))
                    ->visible(fn (AttendancePayrollSnapshot $record): bool => ! $record->isStale())
                    ->requiresConfirmation()
                    ->action(function (AttendancePayrollSnapshot $record, AttendancePayrollReadinessService $service): void {
                        $service->markSnapshotStale($record, 'Marked stale from admin readiness screen.');

                        Notification::make()
                            ->title('Attendance payroll snapshot marked stale.')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->authorize(fn (AttendancePayrollSnapshot $record): bool => Gate::forUser(Auth::user())->allows('cancel', $record))
                    ->visible(fn (AttendancePayrollSnapshot $record): bool => ! $record->isLocked() && ! $record->isCancelled())
                    ->requiresConfirmation()
                    ->action(function (AttendancePayrollSnapshot $record, AttendancePayrollReadinessService $service): void {
                        $user = Auth::user();

                        $service->cancelSnapshot(
                            $record,
                            $user instanceof Employee ? $user : null,
                            'Cancelled from admin readiness screen.',
                        );

                        Notification::make()
                            ->title('Attendance payroll snapshot cancelled.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'company',
            'employee',
            'lockedBy',
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
            && Gate::forUser(Auth::user())->allows('viewAny', AttendancePayrollSnapshot::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendancePayrollSnapshots::route('/'),
        ];
    }

    public static function companyOptions(): array
    {
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
        }

        return Company::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function employeeOptions(): array
    {
        $query = Employee::query()->orderBy('full_name');
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            $query->whereIn('company_id', $user->accessibleCompanyIds());
        }

        return $query->pluck('full_name', 'id')->all();
    }
}
