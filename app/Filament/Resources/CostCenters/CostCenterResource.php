<?php

namespace App\Filament\Resources\CostCenters;

use App\Filament\Resources\CostCenters\Pages\CreateCostCenter;
use App\Filament\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Resources\CostCenters\Pages\ListCostCenters;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Employee;
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

class CostCenterResource extends Resource
{
    protected static ?string $model = CostCenter::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')
                ->label('Company')
                ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                ->required()
                ->disabled(fn (): bool => Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin())
                ->dehydrated(),
            TextInput::make('code')->required()->maxLength(50),
            TextInput::make('name')->required()->maxLength(255),
            Textarea::make('description')->columnSpanFull(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company.name')->label('Company')->toggleable(),
            TextColumn::make('code')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
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

        return $query->forCompany($user->getEffectiveCompanyId());
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee && Auth::user()->canManageHrMasterData();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCostCenters::route('/'),
            'create' => CreateCostCenter::route('/create'),
            'edit' => EditCostCenter::route('/{record}/edit'),
        ];
    }
}
