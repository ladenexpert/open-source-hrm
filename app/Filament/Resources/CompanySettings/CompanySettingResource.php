<?php

namespace App\Filament\Resources\CompanySettings;

use App\Filament\Resources\CompanySettings\Pages\CreateCompanySetting;
use App\Filament\Resources\CompanySettings\Pages\EditCompanySetting;
use App\Filament\Resources\CompanySettings\Pages\ListCompanySettings;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CompanySettingResource extends Resource
{
    protected static ?string $model = CompanySetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 6;

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
            TextInput::make('key')->required()->maxLength(255),
            KeyValue::make('value')->label('Value')->columnSpanFull(),
            Textarea::make('description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company.name')->label('Company')->toggleable(),
            TextColumn::make('key')->searchable()->sortable(),
            TextColumn::make('description')->limit(40)->toggleable(),
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

        return $query->forCompany($user->getEffectiveCompanyId());
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee && Auth::user()->canManageHrMasterData();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanySettings::route('/'),
            'create' => CreateCompanySetting::route('/create'),
            'edit' => EditCompanySetting::route('/{record}/edit'),
        ];
    }
}
