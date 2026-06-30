<?php

namespace App\Filament\Resources\PayrollRuns;

use App\Filament\Resources\PayrollRuns\Pages\CreatePayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Filament\Resources\PayrollRuns\RelationManagers\PayrollRunEmployeesRelationManager;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'run_code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payroll Run')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('payroll_period_id')
                            ->label('Payroll Period')
                            ->options(static::payrollPeriodOptions())
                            ->required()
                            ->live()
                            ->searchable()
                            ->preload(),
                        Select::make('run_type')
                            ->options(PayrollRun::runTypeLabels())
                            ->default(PayrollRun::RUN_TYPE_REGULAR)
                            ->required(),
                        TextInput::make('run_code')
                            ->label('Run Code')
                            ->maxLength(100)
                            ->columnSpanFull(),
                    ]),
                ]),
            Section::make('Initial Preparation')
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('include_all_active_employees')
                            ->label('Include All Active Employees')
                            ->default(false)
                            ->live(),
                        Select::make('employee_ids')
                            ->label('Selected Employees')
                            ->options(fn (callable $get): array => static::employeeOptions($get('payroll_period_id')))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Leave blank to create a draft run, or select employees to prepare immediately.')
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('payrollPeriod.name')->label('Payroll Period')->searchable()->sortable(),
                TextColumn::make('run_code')->label('Run Code')->searchable()->sortable()->toggleable(),
                TextColumn::make('run_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PayrollRun::runTypeLabels()[$state] ?? $state),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PayrollRun::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => PayrollRun::statusColor($state)),
                TextColumn::make('period_start')->date()->sortable(),
                TextColumn::make('period_end')->date()->sortable(),
                TextColumn::make('total_employees')->label('Employees')->sortable(),
                TextColumn::make('ready_employees')->label('Ready')->sortable(),
                TextColumn::make('blocked_employees')->label('Blocked')->sortable(),
                TextColumn::make('prepared_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('locked_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('run_type')
                    ->options(PayrollRun::runTypeLabels()),
                SelectFilter::make('status')
                    ->options(PayrollRun::statusLabels()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'company',
            'payrollPeriod',
            'lockedBy',
            'approvedBy',
            'cancelledBy',
        ]);
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->forCompany($user->getEffectiveCompanyId());
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && Gate::forUser(Auth::user())->allows('viewAny', PayrollRun::class);
    }

    public static function getRelations(): array
    {
        return [
            PayrollRunEmployeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollRuns::route('/'),
            'create' => CreatePayrollRun::route('/create'),
            'view' => ViewPayrollRun::route('/{record}'),
        ];
    }

    public static function payrollPeriodOptions(): \Closure
    {
        return function (): array {
            $query = PayrollPeriod::query()->where('status', '!=', PayrollPeriod::STATUS_CANCELLED)->orderByDesc('period_start');
            $user = Auth::user();

            if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                $query->where('company_id', $user->getEffectiveCompanyId());
            }

            return $query
                ->get()
                ->mapWithKeys(fn (PayrollPeriod $period): array => [
                    $period->id => sprintf(
                        '%s (%s to %s)',
                        $period->name,
                        $period->period_start?->toDateString(),
                        $period->period_end?->toDateString(),
                    ),
                ])
                ->all();
        };
    }

    public static function employeeOptions(mixed $payrollPeriodId): array
    {
        $query = Employee::query()->orderBy('full_name');
        $user = Auth::user();
        $companyId = null;

        if (filled($payrollPeriodId)) {
            $companyId = PayrollPeriod::query()->whereKey($payrollPeriodId)->value('company_id');
        }

        if (blank($companyId) && $user instanceof Employee && ! $user->isSuperAdmin()) {
            $companyId = $user->getEffectiveCompanyId();
        }

        if (blank($companyId)) {
            return [];
        }

        return $query
            ->where('company_id', $companyId)
            ->pluck('full_name', 'id')
            ->all();
    }

    public static function companyOptions(): array
    {
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            return Company::query()
                ->whereKey($user->getEffectiveCompanyId())
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return Company::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
