<?php

namespace App\Filament\Resources\ApprovalWorkflows;

use App\Enums\ApprovalApproverType;
use App\Enums\ApprovalModuleType;
use App\Filament\Resources\ApprovalWorkflows\Pages\CreateApprovalWorkflow;
use App\Filament\Resources\ApprovalWorkflows\Pages\EditApprovalWorkflow;
use App\Filament\Resources\ApprovalWorkflows\Pages\ListApprovalWorkflows;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\Employee;
use App\Models\JobLevel;
use App\Support\OrganizationScope;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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

class ApprovalWorkflowResource extends Resource
{
    protected static ?string $model = ApprovalWorkflow::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Workflow')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_group_id')
                            ->label('Company Group')
                            ->options(static::companyGroupOptions())
                            ->searchable()
                            ->preload(),
                        Select::make('company_id')
                            ->label('Company')
                            ->options(static::companyOptions())
                            ->searchable()
                            ->preload(),
                        TextInput::make('code')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('module_type')
                            ->options(ApprovalModuleType::options())
                            ->required()
                            ->searchable(),
                        Toggle::make('is_active')
                            ->default(true),
                        DatePicker::make('effective_start_date'),
                        DatePicker::make('effective_end_date'),
                    ]),
                    Textarea::make('description')
                        ->columnSpanFull(),
                ]),
            Section::make('Approval Steps')
                ->schema([
                    Repeater::make('steps')
                        ->relationship('steps')
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('step_order')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('approver_type')
                                    ->options(ApprovalApproverType::options())
                                    ->required()
                                    ->searchable(),
                                TextInput::make('approver_role')
                                    ->maxLength(100)
                                    ->helperText('Used when approver type is role.'),
                                Select::make('approver_employee_id')
                                    ->label('Specific Employee')
                                    ->options(static::employeeOptions())
                                    ->searchable()
                                    ->preload(),
                                Select::make('approver_job_level_id')
                                    ->label('Job Level')
                                    ->options(static::jobLevelOptions())
                                    ->searchable()
                                    ->preload(),
                                Toggle::make('is_required')
                                    ->default(true),
                                Toggle::make('can_reject')
                                    ->default(true),
                                Toggle::make('can_return')
                                    ->default(false),
                                Toggle::make('is_final_step')
                                    ->default(false),
                            ]),
                        ])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module_type')
                    ->label('Module')
                    ->badge()
                    ->sortable(),
                TextColumn::make('companyGroup.name')
                    ->label('Company Group')
                    ->toggleable(),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->toggleable(),
                TextColumn::make('steps_count')
                    ->counts('steps')
                    ->label('Steps'),
                TextColumn::make('effective_start_date')
                    ->date()
                    ->toggleable(),
                TextColumn::make('effective_end_date')
                    ->date()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('module_type')
                    ->options(ApprovalModuleType::options()),
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('company_group_id')
                    ->label('Company Group')
                    ->options(static::companyGroupOptions()),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalWorkflows::route('/'),
            'create' => CreateApprovalWorkflow::route('/create'),
            'edit' => EditApprovalWorkflow::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount('steps');
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return OrganizationScope::applyCompanyOrGroupScope($query, $user);
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && Gate::forUser(Auth::user())->allows('viewAny', ApprovalWorkflow::class);
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

    private static function companyGroupOptions(): \Closure
    {
        return function (): array {
            $user = Auth::user();

            if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                return CompanyGroup::query()
                    ->whereKey($user->getEffectiveCompanyGroupId())
                    ->pluck('name', 'id')
                    ->all();
            }

            return CompanyGroup::query()->orderBy('name')->pluck('name', 'id')->all();
        };
    }

    private static function employeeOptions(): \Closure
    {
        return function (): array {
            $query = Employee::query()->orderBy('full_name');
            $user = Auth::user();

            if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                $query->whereIn('company_id', $user->accessibleCompanyIds());
            }

            return $query->pluck('full_name', 'id')->all();
        };
    }

    private static function jobLevelOptions(): \Closure
    {
        return function (): array {
            return JobLevel::query()
                ->when(
                    Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                    fn (Builder $query): Builder => $query->visibleTo(Auth::user()),
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        };
    }
}
