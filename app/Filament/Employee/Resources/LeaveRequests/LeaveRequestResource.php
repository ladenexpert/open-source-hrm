<?php

namespace App\Filament\Employee\Resources\LeaveRequests;

use App\Filament\Employee\Resources\LeaveRequests\Pages\CreateLeaveRequest;
use App\Filament\Employee\Resources\LeaveRequests\Pages\EditLeaveRequest;
use App\Filament\Employee\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Filament\Employee\Resources\LeaveRequests\Pages\ViewLeaveRequest;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Work space';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'My Leave Request';

    protected static ?string $pluralModelLabel = 'My Leave Requests';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Leave Request')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('leave_type_id')
                            ->label('Leave Type')
                            ->options(static::leaveTypeOptions())
                            ->required()
                            ->live()
                            ->searchable()
                            ->preload(),
                        Toggle::make('is_half_day')
                            ->label('Half Day')
                            ->default(false)
                            ->visible(fn (callable $get): bool => static::selectedLeaveTypeAllowsHalfDay($get('leave_type_id'))),
                        DatePicker::make('start_date')
                            ->required(),
                        DatePicker::make('end_date')
                            ->required(),
                        Select::make('half_day_type')
                            ->label('Half Day Type')
                            ->options(LeaveRequest::halfDayTypeOptions())
                            ->visible(fn (callable $get): bool => static::selectedLeaveTypeAllowsHalfDay($get('leave_type_id')) && (bool) $get('is_half_day'))
                            ->nullable(),
                    ]),
                    Textarea::make('reason')
                        ->rows(4)
                        ->columnSpanFull(),
                    FileUpload::make('attachment')
                        ->label('Supporting Attachment')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                        ])
                        ->helperText(fn (callable $get): string => static::selectedLeaveTypeRequiresAttachment($get('leave_type_id'))
                            ? 'Required for the selected leave type. Accepted formats: PDF, JPG, PNG.'
                            : 'Optional. Accepted formats: PDF, JPG, PNG.')
                        ->maxFiles(1)
                        ->storeFiles(false)
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('leaveType.name')
                    ->label('Leave Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')
                    ->state(fn (LeaveRequest $record): string => $record->start_date->toDateString().' - '.$record->end_date->toDateString())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('start_date', $direction)->orderBy('end_date', $direction)),
                TextColumn::make('requested_days')
                    ->label('Requested Days')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LeaveRequest::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => static::statusColor($state)),
                TextColumn::make('submitted_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LeaveRequest::statusOptions()),
                SelectFilter::make('leave_type_id')
                    ->label('Leave Type')
                    ->options(static::leaveTypeOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (LeaveRequest $record): bool => $record->isEditable()),
                Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (LeaveRequest $record): bool => $record->isEditable())
                    ->successNotificationTitle('Leave request submitted successfully.')
                    ->schema([
                        FileUpload::make('attachment')
                            ->label('Supporting Attachment')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->maxFiles(1)
                            ->storeFiles(false)
                            ->nullable(),
                    ])
                    ->action(fn (LeaveRequest $record, array $data) => app(\App\Services\LeaveRequestService::class)->submit(
                        $record,
                        static::resolveUploadedFile($data['attachment'] ?? null),
                    )),
                Action::make('cancel')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->visible(fn (LeaveRequest $record): bool => $record->isCancellable())
                    ->requiresConfirmation()
                    ->successNotificationTitle('Leave request cancelled successfully.')
                    ->schema([
                        Textarea::make('cancellation_reason')
                            ->rows(3),
                    ])
                    ->action(fn (LeaveRequest $record, array $data) => app(\App\Services\LeaveRequestService::class)->cancel(
                        $record,
                        Auth::user(),
                        $data['cancellation_reason'] ?? null,
                    )),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['leaveType', 'attachment'])
            ->latest('id');

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
            'index' => ListLeaveRequests::route('/'),
            'create' => CreateLeaveRequest::route('/create'),
            'view' => ViewLeaveRequest::route('/{record}'),
            'edit' => EditLeaveRequest::route('/{record}/edit'),
        ];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            LeaveRequest::STATUS_DRAFT => 'gray',
            LeaveRequest::STATUS_PENDING => 'warning',
            LeaveRequest::STATUS_APPROVED => 'success',
            LeaveRequest::STATUS_REJECTED => 'danger',
            LeaveRequest::STATUS_CANCELLED => 'secondary',
            default => 'gray',
        };
    }

    public static function resolveUploadedFile(mixed $value): ?UploadedFile
    {
        if ($value instanceof UploadedFile) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof UploadedFile) {
                    return $item;
                }
            }
        }

        return null;
    }

    private static function leaveTypeOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return [];
        }

        return LeaveType::query()
            ->forCompany($user->getEffectiveCompanyId())
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function selectedLeaveTypeAllowsHalfDay(?int $leaveTypeId): bool
    {
        if (blank($leaveTypeId)) {
            return false;
        }

        return (bool) LeaveType::query()->whereKey($leaveTypeId)->value('allow_half_day');
    }

    private static function selectedLeaveTypeRequiresAttachment(?int $leaveTypeId): bool
    {
        if (blank($leaveTypeId)) {
            return false;
        }

        return (bool) LeaveType::query()->whereKey($leaveTypeId)->value('requires_attachment');
    }
}
