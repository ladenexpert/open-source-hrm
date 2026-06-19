<?php

namespace App\Filament\Employee\Resources\AttendanceCorrections;

use App\Filament\Employee\Resources\AttendanceCorrections\Pages\CreateAttendanceCorrection;
use App\Filament\Employee\Resources\AttendanceCorrections\Pages\ListAttendanceCorrections;
use App\Filament\Employee\Resources\AttendanceCorrections\Pages\ViewAttendanceCorrection;
use App\Models\AttendanceSummary;
use App\Filament\Resources\Attendance\AttendanceCorrectionResource\Schemas\AttendanceCorrectionInfolist;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendanceCorrectionService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AttendanceCorrectionResource extends Resource
{
    protected static ?string $model = AttendanceCorrection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Work space';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'My Attendance Correction';

    protected static ?string $pluralModelLabel = 'My Attendance Corrections';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Attendance Correction')
                ->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('attendance_date')->required(),
                        Select::make('correction_type')
                            ->options(AttendanceCorrection::correctionTypeLabels())
                            ->required(),
                        DateTimePicker::make('requested_clock_in_at')->label('Requested Clock In')->seconds(false),
                        DateTimePicker::make('requested_clock_out_at')->label('Requested Clock Out')->seconds(false),
                        Select::make('requested_work_location_id')
                            ->label('Requested Work Location')
                            ->options(static::workLocationOptions())
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),
                    Textarea::make('reason')->required()->rows(4)->columnSpanFull(),
                    Textarea::make('requested_notes')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('correction_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceCorrection::correctionTypeLabels()[$state] ?? $state),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceCorrection::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => AttendanceCorrection::statusColor($state)),
                TextColumn::make('requested_clock_in_at')->dateTime()->toggleable(),
                TextColumn::make('requested_clock_out_at')->dateTime()->toggleable(),
                TextColumn::make('approved_clock_in_at')->dateTime()->toggleable(),
                TextColumn::make('approved_clock_out_at')->dateTime()->toggleable(),
                TextColumn::make('submitted_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceCorrection::statusLabels()),
                SelectFilter::make('correction_type')
                    ->options(AttendanceCorrection::correctionTypeLabels()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (AttendanceCorrection $record): bool => Auth::user() instanceof Employee && Gate::forUser(Auth::user())->allows('submit', $record))
                    ->successNotificationTitle('Attendance correction submitted successfully.')
                    ->action(fn (AttendanceCorrection $record) => app(AttendanceCorrectionService::class)->submit($record, Auth::user())),
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

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceCorrectionInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'attendanceSummary:id,employee_id,attendance_date,status',
                'requestedWorkLocation:id,name',
                'approvedWorkLocation:id,name',
            ])
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
            'index' => ListAttendanceCorrections::route('/'),
            'create' => CreateAttendanceCorrection::route('/create'),
            'view' => ViewAttendanceCorrection::route('/{record}'),
        ];
    }

    public static function getCreateUrlForSummary(?AttendanceSummary $summary = null, ?string $attendanceDate = null): string
    {
        $query = [];

        if ($summary instanceof AttendanceSummary) {
            $query['attendance_summary'] = $summary->getKey();
            $query['attendance_date'] = $summary->attendance_date?->toDateString();
        } elseif (filled($attendanceDate)) {
            $query['attendance_date'] = $attendanceDate;
        }

        $url = static::getUrl('create', panel: 'portal');

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query(array_filter($query, fn (mixed $value): bool => filled($value)));
    }

    public static function workLocationOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return [];
        }

        return WorkLocation::query()
            ->forCompany($user->getEffectiveCompanyId())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
