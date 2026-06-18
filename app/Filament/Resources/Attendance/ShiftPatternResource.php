<?php

namespace App\Filament\Resources\Attendance;

use App\Filament\Resources\Attendance\ShiftPatternResource\Pages\CreateShiftPattern;
use App\Filament\Resources\Attendance\ShiftPatternResource\Pages\EditShiftPattern;
use App\Filament\Resources\Attendance\ShiftPatternResource\Pages\ListShiftPatterns;
use App\Filament\Resources\Attendance\ShiftPatternResource\RelationManagers\ShiftPatternDetailsRelationManager;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ShiftPattern;
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

class ShiftPatternResource extends Resource
{
    protected static ?string $model = ShiftPattern::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shift Pattern')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(static::companyOptions())
                            ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('code')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('color')
                            ->helperText('Use a hex color such as #1D4ED8.')
                            ->maxLength(20),
                        Toggle::make('is_overnight')
                            ->helperText('This is recalculated automatically when shift details are edited.'),
                        Toggle::make('is_active')->default(true),
                    ]),
                    Textarea::make('description')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_overnight')->label('Overnight')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('details_count')->counts('details')->label('Details'),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('is_overnight')
                    ->options([
                        '1' => 'Overnight',
                        '0' => 'Regular',
                    ]),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ShiftPatternDetailsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('company')->withCount('details');
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
            && Gate::forUser(Auth::user())->allows('viewAny', ShiftPattern::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShiftPatterns::route('/'),
            'create' => CreateShiftPattern::route('/create'),
            'edit' => EditShiftPattern::route('/{record}/edit'),
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
}
