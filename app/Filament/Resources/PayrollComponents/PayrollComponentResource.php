<?php

namespace App\Filament\Resources\PayrollComponents;

use App\Filament\Resources\PayrollComponents\Pages\CreatePayrollComponent;
use App\Filament\Resources\PayrollComponents\Pages\EditPayrollComponent;
use App\Filament\Resources\PayrollComponents\Pages\ListPayrollComponents;
use App\Models\Company;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\PayrollComponent;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Facades\Gate;

class PayrollComponentResource extends Resource
{
    protected static ?string $model = PayrollComponent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Payroll Component';

    protected static ?string $pluralModelLabel = 'Payroll Components';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payroll Component')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(static::companyOptions())
                            ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                            ->required()
                            ->disabled(fn (): bool => Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin())
                            ->dehydrated()
                            ->searchable()
                            ->preload(),
                        TextInput::make('component_code')
                            ->label('Component Code')
                            ->maxLength(100),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('component_type')
                            ->options(PayrollComponent::componentTypeLabels())
                            ->required(),
                        Select::make('value_type')
                            ->options(PayrollComponent::valueTypeLabels())
                            ->required()
                            ->live(),
                        TextInput::make('default_amount')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn ($get): bool => in_array($get('value_type'), [
                                PayrollComponent::VALUE_TYPE_FIXED,
                                PayrollComponent::VALUE_TYPE_MANUAL,
                            ], true)),
                        TextInput::make('default_percentage')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn ($get): bool => $get('value_type') === PayrollComponent::VALUE_TYPE_PERCENTAGE),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('active')
                            ->default(true),
                    ]),
                ]),
            Section::make('Flags')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('taxable')->default(false),
                        Toggle::make('tax_deductible')->default(false),
                        Toggle::make('bpjs_applicable')->default(false)->label('BPJS Applicable'),
                        Toggle::make('thr_applicable')->default(false)->label('THR Applicable'),
                        Toggle::make('proratable')->default(false),
                        Toggle::make('recurring')->default(true),
                    ]),
                ]),
            Section::make('Applicability Metadata')
                ->schema([
                    CheckboxList::make('metadata.applicability.employment_status_codes')
                        ->label('Employment Statuses')
                        ->options(static::employmentStatusOptions())
                        ->columns(2),
                    CheckboxList::make('metadata.applicability.employment_type_codes')
                        ->label('Employment Types')
                        ->options(static::employmentTypeOptions())
                        ->columns(2),
                    CheckboxList::make('metadata.applicability.contract_type_codes')
                        ->label('Contract Types')
                        ->options(static::contractTypeOptions())
                        ->columns(2),
                    Grid::make(2)->schema([
                        TagsInput::make('metadata.applicability.payroll_schemes')
                            ->label('Payroll Schemes')
                            ->placeholder('Add payroll scheme'),
                        TagsInput::make('metadata.applicability.employee_groups')
                            ->label('Employee Groups')
                            ->placeholder('Add employee group'),
                    ]),
                    Grid::make(3)->schema([
                        Toggle::make('metadata.applicability.expatriate_applicable')
                            ->label('Expatriate Applicable'),
                        Toggle::make('metadata.applicability.daily_worker_applicable')
                            ->label('Daily Worker Applicable'),
                        Toggle::make('metadata.applicability.intern_applicable')
                            ->label('Intern Applicable'),
                        Toggle::make('metadata.applicability.probation_applicable')
                            ->label('Probation Applicable'),
                        Toggle::make('metadata.applicability.part_time_applicable')
                            ->label('Part Time Applicable'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('sort_order')->sortable()->toggleable(),
                TextColumn::make('component_code')->label('Code')->searchable()->sortable()->toggleable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('component_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PayrollComponent::componentTypeLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => PayrollComponent::componentTypeColor($state)),
                TextColumn::make('value_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PayrollComponent::valueTypeLabels()[$state] ?? $state)
                    ->color('gray'),
                TextColumn::make('default_amount')
                    ->label('Default Amount')
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? '-' : number_format((float) $state, 2)),
                TextColumn::make('default_percentage')
                    ->label('Default %')
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? '-' : number_format((float) $state, 4).' %'),
                IconColumn::make('taxable')->boolean()->label('Taxable'),
                IconColumn::make('tax_deductible')->boolean()->label('Tax Deductible')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('bpjs_applicable')->boolean()->label('BPJS'),
                IconColumn::make('thr_applicable')->boolean()->label('THR'),
                IconColumn::make('proratable')->boolean()->label('Proration'),
                IconColumn::make('recurring')->boolean()->label('Recurring'),
                IconColumn::make('active')->boolean()->label('Active'),
                TextColumn::make('applicability_summary')
                    ->label('Applicability')
                    ->state(fn (PayrollComponent $record): string => $record->applicabilitySummary())
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyFilterOptions()),
                SelectFilter::make('component_type')
                    ->label('Component Type')
                    ->options(PayrollComponent::componentTypeLabels()),
                SelectFilter::make('active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! array_key_exists('value', $data) || $data['value'] === null || $data['value'] === '') {
                            return $query;
                        }

                        return $query->where('active', $data['value'] === '1');
                    }),
            ])
            ->recordActions([
                Action::make('activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PayrollComponent $record): bool => ! $record->active)
                    ->requiresConfirmation()
                    ->action(fn (PayrollComponent $record): bool => $record->update(['active' => true])),
                Action::make('deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PayrollComponent $record): bool => $record->active)
                    ->requiresConfirmation()
                    ->action(fn (PayrollComponent $record): bool => $record->update(['active' => false])),
                EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company']);
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
            && Gate::forUser(Auth::user())->allows('viewAny', PayrollComponent::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollComponents::route('/'),
            'create' => CreatePayrollComponent::route('/create'),
            'edit' => EditPayrollComponent::route('/{record}/edit'),
        ];
    }

    private static function companyOptions(): \Closure
    {
        return function (): array {
            $user = Auth::user();

            if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                return Company::query()
                    ->whereKey($user->getEffectiveCompanyId())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all();
            }

            return Company::query()->orderBy('name')->pluck('name', 'id')->all();
        };
    }

    /**
     * @return array<int|string, string>
     */
    private static function companyFilterOptions(): array
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

    /**
     * @return array<int|string, string>
     */
    private static function employmentStatusOptions(): array
    {
        return static::scopedMasterOptions(EmploymentStatus::class);
    }

    /**
     * @return array<int|string, string>
     */
    private static function employmentTypeOptions(): array
    {
        return static::scopedMasterOptions(EmploymentType::class);
    }

    /**
     * @return array<int|string, string>
     */
    private static function contractTypeOptions(): array
    {
        return static::scopedMasterOptions(ContractType::class);
    }

    /**
     * @param  class-string<EmploymentStatus|EmploymentType|ContractType>  $modelClass
     * @return array<int|string, string>
     */
    private static function scopedMasterOptions(string $modelClass): array
    {
        $query = $modelClass::query()->orderBy('sort_order')->orderBy('name');
        $user = Auth::user();

        if ($user instanceof Employee) {
            $query->visibleTo($user);
        }

        return $query
            ->get()
            ->mapWithKeys(fn ($record): array => [$record->code => "{$record->code} - {$record->name}"])
            ->all();
    }
}
