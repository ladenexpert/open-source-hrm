<?php

namespace App\Filament\Resources\WorkdayPatterns;

use App\Filament\Resources\WorkdayPatterns\Pages\CreateWorkdayPattern;
use App\Filament\Resources\WorkdayPatterns\Pages\EditWorkdayPattern;
use App\Filament\Resources\WorkdayPatterns\Pages\ListWorkdayPatterns;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkdayPattern;
use App\Models\WorkdayPatternDay;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class WorkdayPatternResource extends Resource
{
    protected static ?string $model = WorkdayPattern::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Workday Pattern')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(static::companyOptions())
                            ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ]),
                    Textarea::make('description')
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        Toggle::make('is_default')->default(false),
                        Toggle::make('is_active')->default(true),
                    ]),
                ]),
            Section::make('Pattern Days')
                ->schema([
                    Repeater::make('days')
                        ->relationship('days')
                        ->default(static::defaultDayRows())
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->schema([
                            Grid::make(3)->schema([
                                Select::make('day_of_week')
                                    ->label('Day')
                                    ->options(WorkdayPatternDay::dayOptions())
                                    ->required(),
                                Toggle::make('is_working_day')
                                    ->label('Working Day')
                                    ->default(true)
                                    ->live(),
                                TextInput::make('working_hours')
                                    ->numeric()
                                    ->minValue(0)
                                    ->readOnly(fn (callable $get): bool => ! (bool) $get('is_working_day'))
                                    ->nullable(),
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
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('days_count')->counts('days')->label('Days'),
                IconColumn::make('is_default')->label('Default')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('is_default')
                    ->options([
                        '1' => 'Default',
                        '0' => 'Non-default',
                    ]),
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
            'index' => ListWorkdayPatterns::route('/'),
            'create' => CreateWorkdayPattern::route('/create'),
            'edit' => EditWorkdayPattern::route('/{record}/edit'),
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

    private static function defaultDayRows(): array
    {
        return [
            ['day_of_week' => 1, 'is_working_day' => true, 'working_hours' => 8],
            ['day_of_week' => 2, 'is_working_day' => true, 'working_hours' => 8],
            ['day_of_week' => 3, 'is_working_day' => true, 'working_hours' => 8],
            ['day_of_week' => 4, 'is_working_day' => true, 'working_hours' => 8],
            ['day_of_week' => 5, 'is_working_day' => true, 'working_hours' => 8],
            ['day_of_week' => 6, 'is_working_day' => false, 'working_hours' => null],
            ['day_of_week' => 7, 'is_working_day' => false, 'working_hours' => null],
        ];
    }
}
