<?php

namespace App\Filament\Resources\WorkLocations;

use App\Filament\Resources\WorkLocations\Pages\CreateWorkLocation;
use App\Filament\Resources\WorkLocations\Pages\EditWorkLocation;
use App\Filament\Resources\WorkLocations\Pages\ListWorkLocations;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLocation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class WorkLocationResource extends Resource
{
    protected static ?string $model = WorkLocation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')
                ->label('Company')
                ->options(function (): array {
                    $user = Auth::user();

                    if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                        return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
                    }

                    return Company::query()->orderBy('name')->pluck('name', 'id')->all();
                })
                ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                ->required()
                ->searchable()
                ->preload(),
            Select::make('branch_id')
                ->label('Branch')
                ->options(function (): array {
                    $query = Branch::query()->orderBy('name');

                    if (Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin()) {
                        $query->forCompanies(Auth::user()->accessibleCompanyIds());
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->nullable(),
            TextInput::make('code')->required()->maxLength(50),
            TextInput::make('name')->required()->maxLength(255),
            Textarea::make('address')->columnSpanFull(),
            TextInput::make('latitude')
                ->label('Latitude')
                ->numeric()
                ->minValue(-90)
                ->maxValue(90)
                ->step('0.0000001'),
            TextInput::make('longitude')
                ->label('Longitude')
                ->numeric()
                ->minValue(-180)
                ->maxValue(180)
                ->step('0.0000001'),
            TextInput::make('radius_meters')
                ->label('Radius (Meters)')
                ->numeric()
                ->minValue(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company.name')->label('Company')->toggleable(),
            TextColumn::make('branch.name')->label('Branch')->toggleable(),
            TextColumn::make('code')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('latitude')
                ->label('Latitude')
                ->formatStateUsing(fn ($state): string => filled($state) ? number_format((float) $state, 7, '.', '') : '-')
                ->toggleable(),
            TextColumn::make('longitude')
                ->label('Longitude')
                ->formatStateUsing(fn ($state): string => filled($state) ? number_format((float) $state, 7, '.', '') : '-')
                ->toggleable(),
            TextColumn::make('radius_meters')
                ->label('Radius (Meters)')
                ->toggleable(),
            IconColumn::make('is_active')->boolean(),
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
            'index' => ListWorkLocations::route('/'),
            'create' => CreateWorkLocation::route('/create'),
            'edit' => EditWorkLocation::route('/{record}/edit'),
        ];
    }
}
