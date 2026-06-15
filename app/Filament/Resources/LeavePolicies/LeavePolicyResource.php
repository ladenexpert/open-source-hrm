<?php

namespace App\Filament\Resources\LeavePolicies;

use App\Filament\Resources\LeavePolicies\Pages\CreateLeavePolicy;
use App\Filament\Resources\LeavePolicies\Pages\EditLeavePolicy;
use App\Filament\Resources\LeavePolicies\Pages\ListLeavePolicies;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\JobLevel;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeavePolicyResource extends Resource
{
    protected static ?string $model = LeavePolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'leave_type_id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Leave Policy')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(static::companyOptions())
                            ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                            ->required()
                            ->live()
                            ->searchable()
                            ->preload(),
                        Select::make('leave_type_id')
                            ->label('Leave Type')
                            ->options(fn (callable $get): array => static::leaveTypeOptions($get('company_id')))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('employment_status_id')
                            ->label('Employment Status')
                            ->options(fn (callable $get): array => static::scopedMasterDataOptions(EmploymentStatus::class, $get('company_id')))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('job_level_id')
                            ->label('Job Level')
                            ->options(fn (callable $get): array => static::scopedMasterDataOptions(JobLevel::class, $get('company_id')))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('entitlement_days')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        TextInput::make('minimum_service_months')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        DatePicker::make('effective_from')
                            ->required(),
                        DatePicker::make('effective_until')
                            ->nullable(),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('leaveType.name')->label('Leave Type')->searchable()->sortable(),
                TextColumn::make('employmentStatus.name')->label('Employment Status')->toggleable(),
                TextColumn::make('jobLevel.name')->label('Job Level')->toggleable(),
                TextColumn::make('entitlement_days')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('minimum_service_months')->sortable(),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_until')->date()->toggleable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('leave_type_id')
                    ->label('Leave Type')
                    ->options(fn (): array => LeaveType::query()
                        ->when(
                            Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                            fn (Builder $query): Builder => $query->forCompanies(Auth::user()->accessibleCompanyIds()),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
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
        return Auth::user() instanceof Employee && Auth::user()->canManageHrMasterData();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeavePolicies::route('/'),
            'create' => CreateLeavePolicy::route('/create'),
            'edit' => EditLeavePolicy::route('/{record}/edit'),
        ];
    }

    private static function companyOptions(): \Closure
    {
        return function (): array {
            $user = Auth::user();

            if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
            }

            return Company::query()->orderBy('name')->pluck('name', 'id')->all();
        };
    }

    private static function leaveTypeOptions(?int $companyId): array
    {
        return LeaveType::query()
            ->when(filled($companyId), fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->when(
                blank($companyId) && Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                fn (Builder $query): Builder => $query->forCompanies(Auth::user()->accessibleCompanyIds()),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function scopedMasterDataOptions(string $modelClass, ?int $companyId): array
    {
        $query = $modelClass::query()->orderBy('sort_order')->orderBy('name');
        $user = Auth::user();

        if (filled($companyId)) {
            $companyGroupId = Company::query()->whereKey($companyId)->value('company_group_id');

            $query->where(function (Builder $scope) use ($companyId, $companyGroupId): void {
                $scope->where(function (Builder $globalScope): void {
                    $globalScope
                        ->whereNull('company_id')
                        ->whereNull('company_group_id');
                });

                if (filled($companyGroupId)) {
                    $scope->orWhere('company_group_id', $companyGroupId);
                }

                $scope->orWhere('company_id', $companyId);
            });
        } elseif ($user instanceof Employee && ! $user->isSuperAdmin()) {
            $query->visibleTo($user);
        }

        return $query->pluck('name', 'id')->all();
    }
}
