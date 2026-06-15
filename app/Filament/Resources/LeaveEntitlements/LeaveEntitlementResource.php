<?php

namespace App\Filament\Resources\LeaveEntitlements;

use App\Filament\Resources\LeaveEntitlements\Pages\ListLeaveEntitlements;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeaveEntitlementResource extends Resource
{
    protected static ?string $model = LeaveEntitlement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Leave Entitlement';

    protected static ?string $pluralModelLabel = 'Leave Entitlements';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable()->sortable(),
                TextColumn::make('leaveType.name')->label('Leave Type')->searchable()->sortable(),
                TextColumn::make('year')->sortable(),
                TextColumn::make('entitled_days')->label('Entitled')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('carried_forward_days')->label('Carried Forward')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('used_days')->label('Used')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('remaining_days')->label('Remaining')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('expires_at')->label('Expiry')->date()->sortable()->toggleable(),
                TextColumn::make('updated_at')->since()->label('Updated')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('year')
                    ->options(static::yearOptions()),
                SelectFilter::make('leave_type_id')
                    ->label('Leave Type')
                    ->options(static::leaveTypeOptions()),
            ])
            ->recordActions([
                Action::make('adjust_balance')
                    ->label('Adjust Balance')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->authorize(fn (LeaveEntitlement $record): bool => Gate::forUser(Auth::user())->allows('update', $record))
                    ->schema([
                        TextInput::make('days')
                            ->label('Adjustment Days')
                            ->required()
                            ->numeric()
                            ->helperText('Use a positive value to increase balance or a negative value to reduce it.'),
                        Textarea::make('remarks')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (LeaveEntitlement $record, array $data, LeaveBalanceService $leaveBalanceService): void {
                        $leaveBalanceService->adjustBalance(
                            $record,
                            (float) $data['days'],
                            (string) $data['remarks'],
                        );

                        Notification::make()
                            ->title('Leave balance adjusted successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'employee', 'leaveType']);
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
            && Gate::forUser(Auth::user())->allows('viewAny', LeaveEntitlement::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveEntitlements::route('/'),
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

    private static function yearOptions(): array
    {
        return LeaveEntitlement::query()
            ->when(
                Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                fn (Builder $query): Builder => $query->forCompanies(Auth::user()->accessibleCompanyIds()),
            )
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year', 'year')
            ->mapWithKeys(fn ($year, $key): array => [(string) $key => (string) $year])
            ->all();
    }

    private static function leaveTypeOptions(): array
    {
        return LeaveType::query()
            ->when(
                Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                fn (Builder $query): Builder => $query->forCompanies(Auth::user()->accessibleCompanyIds()),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
