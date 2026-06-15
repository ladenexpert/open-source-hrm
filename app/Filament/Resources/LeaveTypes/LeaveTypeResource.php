<?php

namespace App\Filament\Resources\LeaveTypes;

use App\Filament\Resources\LeaveTypes\Pages\CreateLeaveType;
use App\Filament\Resources\LeaveTypes\Pages\EditLeaveType;
use App\Filament\Resources\LeaveTypes\Pages\ListLeaveTypes;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveType;
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

class LeaveTypeResource extends Resource
{
    protected static ?string $model = LeaveType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Leave Type')
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
                        TextInput::make('max_carry_forward_days')
                            ->label('Max Carry Forward Days')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
                    ]),
                    Textarea::make('description')
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        Toggle::make('is_paid')->default(true),
                        Toggle::make('requires_attachment')->default(false),
                        Toggle::make('allow_half_day')->default(true),
                        Toggle::make('allow_carry_forward')->default(false),
                        Toggle::make('is_active')->default(true),
                    ]),
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
                IconColumn::make('is_paid')->label('Paid')->boolean(),
                IconColumn::make('requires_attachment')->label('Attachment')->boolean()->toggleable(),
                IconColumn::make('allow_half_day')->label('Half Day')->boolean()->toggleable(),
                IconColumn::make('allow_carry_forward')->label('Carry Forward')->boolean()->toggleable(),
                TextColumn::make('max_carry_forward_days')->label('Max Carry Forward')->numeric(decimalPlaces: 2)->toggleable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('is_paid')
                    ->options([
                        '1' => 'Paid',
                        '0' => 'Unpaid',
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
            'index' => ListLeaveTypes::route('/'),
            'create' => CreateLeaveType::route('/create'),
            'edit' => EditLeaveType::route('/{record}/edit'),
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
