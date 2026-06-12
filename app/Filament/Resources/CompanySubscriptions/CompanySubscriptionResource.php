<?php

namespace App\Filament\Resources\CompanySubscriptions;

use App\Filament\Resources\CompanySubscriptions\Pages\CreateCompanySubscription;
use App\Filament\Resources\CompanySubscriptions\Pages\EditCompanySubscription;
use App\Filament\Resources\CompanySubscriptions\Pages\ListCompanySubscriptions;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Employee;
use App\Models\SubscriptionPlan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CompanySubscriptionResource extends Resource
{
    protected static ?string $model = CompanySubscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')
                ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->searchable(),
            Select::make('subscription_plan_id')
                ->label('Subscription Plan')
                ->options(fn (): array => SubscriptionPlan::query()->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->searchable(),
            DatePicker::make('start_date')->required(),
            DatePicker::make('end_date'),
            TextInput::make('status')->required()->maxLength(50),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company.name')->label('Company')->searchable()->sortable(),
            TextColumn::make('subscriptionPlan.name')->label('Plan')->searchable()->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('start_date')->date()->sortable(),
            TextColumn::make('end_date')->date()->sortable(),
        ]);
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee && Auth::user()->isSuperAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanySubscriptions::route('/'),
            'create' => CreateCompanySubscription::route('/create'),
            'edit' => EditCompanySubscription::route('/{record}/edit'),
        ];
    }
}
