<?php

namespace App\Filament\Resources\Attendance;

use App\Filament\Resources\Attendance\EmployeeDeviceResource\Pages\ListEmployeeDevices;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Services\Attendance\AttendanceDeviceTrustService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EmployeeDeviceResource extends Resource
{
    protected static ?string $model = EmployeeDevice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Employee Device';

    protected static ?string $pluralModelLabel = 'Employee Devices';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_used_at', 'desc')
            ->columns([
                TextColumn::make('company.name')
                    ->label('Company')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('device_name')
                    ->label('Device')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('browser')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('platform')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => EmployeeDevice::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        EmployeeDevice::STATUS_TRUSTED => 'success',
                        EmployeeDevice::STATUS_REVOKED => 'danger',
                        EmployeeDevice::STATUS_INACTIVE => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('first_seen_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('trusted_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('revoked_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('last_ip_address')
                    ->label('Last IP')
                    ->toggleable(),
                TextColumn::make('status_reason')
                    ->label('Reason')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->options(static::employeeOptions())
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(EmployeeDevice::statusLabels()),
                Filter::make('last_used_at')
                    ->schema([
                        DatePicker::make('date')->label('Last Used Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['date'] ?? null)) {
                            return $query;
                        }

                        return $query->whereDate('last_used_at', $data['date']);
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('trust')
                        ->label('Trust')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (EmployeeDevice $record): bool => ! $record->canBeUsedForAttendance())
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('status_reason')
                                ->label('Reason')
                                ->rows(3),
                        ])
                        ->action(function (EmployeeDevice $record, array $data): void {
                            $actor = Auth::user();

                            if (! $actor instanceof Employee) {
                                return;
                            }

                            app(AttendanceDeviceTrustService::class)->trustDevice(
                                $record,
                                $actor,
                                $data['status_reason'] ?? null,
                            );
                        })
                        ->successNotificationTitle('Device trusted.'),
                    Action::make('revoke')
                        ->label('Revoke')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (EmployeeDevice $record): bool => $record->status !== EmployeeDevice::STATUS_REVOKED)
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('status_reason')
                                ->label('Reason')
                                ->rows(3),
                        ])
                        ->action(function (EmployeeDevice $record, array $data): void {
                            $actor = Auth::user();

                            if (! $actor instanceof Employee) {
                                return;
                            }

                            app(AttendanceDeviceTrustService::class)->revokeDevice(
                                $record,
                                $actor,
                                $data['status_reason'] ?? null,
                            );
                        })
                        ->successNotificationTitle('Device revoked.'),
                    Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-pause-circle')
                        ->color('gray')
                        ->visible(fn (EmployeeDevice $record): bool => $record->status !== EmployeeDevice::STATUS_INACTIVE)
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('status_reason')
                                ->label('Reason')
                                ->rows(3),
                        ])
                        ->action(function (EmployeeDevice $record, array $data): void {
                            $actor = Auth::user();

                            if (! $actor instanceof Employee) {
                                return;
                            }

                            app(AttendanceDeviceTrustService::class)->deactivateDevice(
                                $record,
                                $actor,
                                $data['status_reason'] ?? null,
                            );
                        })
                        ->successNotificationTitle('Device deactivated.'),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'company',
            'employee',
            'trustedBy',
            'revokedBy',
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
            && Gate::forUser(Auth::user())->allows('viewAny', EmployeeDevice::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingDeviceCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::pendingDeviceCount() > 0 ? 'warning' : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeDevices::route('/'),
        ];
    }

    private static function pendingDeviceCount(): int
    {
        $user = Auth::user();
        $query = EmployeeDevice::query()->status(EmployeeDevice::STATUS_PENDING);

        if (! $user instanceof Employee) {
            return 0;
        }

        if (! $user->isSuperAdmin()) {
            $query->forCompanies($user->accessibleCompanyIds());
        }

        return $query->count();
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
