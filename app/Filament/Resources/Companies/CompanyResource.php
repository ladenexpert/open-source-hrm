<?php

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Models\Company;
use App\Models\CompanyGroup;
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

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_group_id')
                ->label('Company Group')
                ->options(function (): array {
                    $user = Auth::user();

                    if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                        return CompanyGroup::query()
                            ->whereKey($user->getEffectiveCompanyGroupId())
                            ->pluck('name', 'id')
                            ->all();
                    }

                    return CompanyGroup::query()->orderBy('name')->pluck('name', 'id')->all();
                })
                ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyGroupId() : null)
                ->searchable()
                ->preload(),
            Select::make('parent_company_id')
                ->label('Parent Company')
                ->options(function (): array {
                    $user = Auth::user();
                    $query = Company::query()->orderBy('name');

                    if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                        $query->whereIn('id', $user->accessibleCompanyIds());
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->preload(),
            TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('legal_name')->maxLength(255),
            TextInput::make('email')->email()->maxLength(255),
            TextInput::make('phone')->maxLength(50),
            TextInput::make('tax_id')->maxLength(100),
            Select::make('company_type')
                ->options([
                    'holding' => 'Holding',
                    'subsidiary' => 'Subsidiary',
                    'branch_entity' => 'Branch Entity',
                    'external' => 'External',
                ]),
            Textarea::make('address')->columnSpanFull(),
            Toggle::make('is_legal_entity')->default(true),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('companyGroup.name')->label('Company Group')->toggleable(),
                TextColumn::make('parentCompany.name')->label('Parent Company')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('company_type')->badge(),
                IconColumn::make('is_legal_entity')->boolean()->label('Legal Entity'),
                TextColumn::make('legal_name')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
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

        return $query->whereIn('id', $user->accessibleCompanyIds());
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee && Auth::user()->canManageHrMasterData();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
