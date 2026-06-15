<?php

namespace App\Filament\Resources\MasterData;

use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\Employee;
use App\Support\OrganizationScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

abstract class ScopedMasterDataResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'HR Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(array_merge(
            [
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
                Select::make('company_id')
                    ->label('Company')
                    ->options(function (): array {
                        $user = Auth::user();

                        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                            return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
                        }

                        return Company::query()->orderBy('name')->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->preload(),
            ],
            static::additionalFormComponents(),
            [
                TextInput::make('code')
                    ->required()
                    ->maxLength(50),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(array_merge(
                static::additionalTableColumns(),
                [
                    TextColumn::make('code')->searchable()->sortable(),
                    TextColumn::make('name')->searchable()->sortable(),
                    TextColumn::make('companyGroup.name')->label('Company Group')->toggleable(),
                    TextColumn::make('company.name')->label('Company')->toggleable(),
                    TextColumn::make('sort_order')->sortable()->toggleable(isToggledHiddenByDefault: true),
                    IconColumn::make('is_active')->boolean(),
                ],
            ))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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

        return OrganizationScope::applyMasterDataScope($query, $user);
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee && Auth::user()->canManageHrMasterData();
    }

    protected static function additionalFormComponents(): array
    {
        return [];
    }

    protected static function additionalTableColumns(): array
    {
        return [];
    }
}
