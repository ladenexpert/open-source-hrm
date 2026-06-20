<?php

namespace App\Filament\Resources\Attendance;

use App\Filament\Resources\Attendance\AttendanceCorrectionResource\Pages\ListAttendanceCorrections;
use App\Filament\Resources\Attendance\AttendanceCorrectionResource\Pages\ViewAttendanceCorrection;
use App\Models\AttendanceCorrection;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendanceCorrectionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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

class AttendanceCorrectionResource extends Resource
{
    protected static ?string $model = AttendanceCorrection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Attendance Correction';

    protected static ?string $pluralModelLabel = 'Attendance Corrections';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Correction Request')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('company.name')->label('Company')->disabled(),
                        TextInput::make('employee.full_name')->label('Employee')->disabled(),
                        TextInput::make('attendance_date')->disabled(),
                        TextInput::make('correction_type')
                            ->formatStateUsing(fn (?string $state): string => AttendanceCorrection::correctionTypeLabels()[$state] ?? (string) $state)
                            ->disabled(),
                        TextInput::make('status')
                            ->formatStateUsing(fn (?string $state): string => AttendanceCorrection::statusLabels()[$state] ?? (string) $state)
                            ->disabled(),
                        TextInput::make('submitted_at')->disabled(),
                        TextInput::make('requested_clock_in_at')->disabled(),
                        TextInput::make('requested_clock_out_at')->disabled(),
                        TextInput::make('requestedWorkLocation.name')->label('Requested Work Location')->disabled(),
                        TextInput::make('approvedWorkLocation.name')->label('Approved Work Location')->disabled(),
                        TextInput::make('approved_clock_in_at')->disabled(),
                        TextInput::make('approved_clock_out_at')->disabled(),
                    ]),
                    Textarea::make('reason')->rows(3)->disabled()->columnSpanFull(),
                    Textarea::make('requested_notes')->rows(3)->disabled()->columnSpanFull(),
                    Textarea::make('approved_notes')->rows(3)->disabled()->columnSpanFull(),
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
                TextColumn::make('correction_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceCorrection::correctionTypeLabels()[$state] ?? $state),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceCorrection::statusLabels()[$state] ?? $state),
                TextColumn::make('requested_clock_in_at')->dateTime()->toggleable(),
                TextColumn::make('requested_clock_out_at')->dateTime()->toggleable(),
                TextColumn::make('approved_clock_in_at')->dateTime()->toggleable(),
                TextColumn::make('approved_clock_out_at')->dateTime()->toggleable(),
                TextColumn::make('submitted_at')->dateTime()->toggleable(),
                TextColumn::make('approved_at')->dateTime()->toggleable(),
                TextColumn::make('rejected_at')->dateTime()->toggleable(),
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
                SelectFilter::make('correction_type')
                    ->options(AttendanceCorrection::correctionTypeLabels()),
                SelectFilter::make('status')
                    ->options(AttendanceCorrection::statusLabels()),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (AttendanceCorrection $record): string => static::getUrl('view', ['record' => $record])),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AttendanceCorrection $record): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('approve', $record))
                    ->fillForm(fn (AttendanceCorrection $record): array => [
                        'approved_clock_in_at' => $record->requested_clock_in_at,
                        'approved_clock_out_at' => $record->requested_clock_out_at,
                        'approved_work_location_id' => $record->requested_work_location_id,
                        'approved_notes' => $record->requested_notes,
                    ])
                    ->schema([
                        DateTimePicker::make('approved_clock_in_at')->label('Approved Clock In')->seconds(false),
                        DateTimePicker::make('approved_clock_out_at')->label('Approved Clock Out')->seconds(false),
                        Select::make('approved_work_location_id')
                            ->label('Approved Work Location')
                            ->options(fn () => static::workLocationOptions())
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Textarea::make('approved_notes')->rows(3),
                        Textarea::make('comments')->label('Approval Notes')->rows(3),
                    ])
                    ->action(function (AttendanceCorrection $record, array $data): void {
                        $payload = [
                            'approved_clock_in_at' => $data['approved_clock_in_at'] ?? null,
                            'approved_clock_out_at' => $data['approved_clock_out_at'] ?? null,
                            'approved_work_location_id' => $data['approved_work_location_id'] ?? null,
                            'approved_notes' => $data['approved_notes'] ?? null,
                        ];

                        if ($record->approvalRequest) {
                            app(AttendanceCorrectionService::class)->processApproval(
                                $record->approvalRequest,
                                Auth::user(),
                                'approved',
                                $data['comments'] ?? null,
                                $payload,
                            );
                        } else {
                            app(AttendanceCorrectionService::class)->approve($record, Auth::user(), $payload);
                        }

                        Notification::make()
                            ->title('Attendance correction approved.')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (AttendanceCorrection $record): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('reject', $record))
                    ->schema([
                        Textarea::make('comments')->label('Rejection Notes')->required()->rows(3),
                    ])
                    ->action(function (AttendanceCorrection $record, array $data): void {
                        if ($record->approvalRequest) {
                            app(AttendanceCorrectionService::class)->processApproval(
                                $record->approvalRequest,
                                Auth::user(),
                                'rejected',
                                $data['comments'] ?? null,
                            );
                        } else {
                            app(AttendanceCorrectionService::class)->reject($record, Auth::user(), $data['comments'] ?? null);
                        }

                        Notification::make()
                            ->title('Attendance correction rejected.')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->visible(fn (AttendanceCorrection $record): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('cancel', $record))
                    ->requiresConfirmation()
                    ->action(function (AttendanceCorrection $record): void {
                        app(AttendanceCorrectionService::class)->cancel($record, Auth::user());

                        Notification::make()
                            ->title('Attendance correction cancelled.')
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
            'requestedWorkLocation',
            'approvedWorkLocation',
            'approvalRequest',
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
            && Auth::user()->canManageHrMasterData();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceCorrections::route('/'),
            'view' => ViewAttendanceCorrection::route('/{record}'),
        ];
    }

    public static function workLocationOptions(): array
    {
        $query = WorkLocation::query()->orderBy('name');
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            $query->forCompanies($user->accessibleCompanyIds());
        }

        return $query->pluck('name', 'id')->all();
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
