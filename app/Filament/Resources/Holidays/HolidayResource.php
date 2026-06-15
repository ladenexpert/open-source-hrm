<?php

namespace App\Filament\Resources\Holidays;

use App\Filament\Resources\Holidays\Pages\CreateHoliday;
use App\Filament\Resources\Holidays\Pages\EditHoliday;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions())
                    ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                    ->required()
                    ->live()
                    ->searchable()
                    ->preload(),
                Select::make('holiday_calendar_id')
                    ->label('Holiday Calendar')
                    ->options(fn (callable $get): array => static::holidayCalendarOptions($get('company_id')))
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('date')
                    ->required(),
                Select::make('type')
                    ->options(Holiday::typeOptions())
                    ->default(Holiday::TYPE_COMPANY)
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_paid')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('holidayCalendar.name')->label('Calendar')->searchable()->sortable(),
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                IconColumn::make('is_paid')->label('Paid')->boolean(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('type')
                    ->options(Holiday::typeOptions()),
                SelectFilter::make('holiday_calendar_id')
                    ->label('Holiday Calendar')
                    ->options(fn (): array => HolidayCalendar::query()
                        ->when(
                            Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                            fn (Builder $query): Builder => $query->forCompanies(Auth::user()->accessibleCompanyIds()),
                        )
                        ->orderBy('year')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
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
            'index' => ListHolidays::route('/'),
            'create' => CreateHoliday::route('/create'),
            'edit' => EditHoliday::route('/{record}/edit'),
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

    private static function holidayCalendarOptions(?int $companyId): array
    {
        return HolidayCalendar::query()
            ->when(filled($companyId), fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->when(
                blank($companyId) && Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                fn (Builder $query): Builder => $query->forCompanies(Auth::user()->accessibleCompanyIds()),
            )
            ->orderByDesc('year')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
