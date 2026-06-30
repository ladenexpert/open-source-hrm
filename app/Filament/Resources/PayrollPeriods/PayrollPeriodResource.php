<?php

namespace App\Filament\Resources\PayrollPeriods;

use App\Filament\Resources\PayrollPeriods\Pages\CreatePayrollPeriod;
use App\Filament\Resources\PayrollPeriods\Pages\EditPayrollPeriod;
use App\Filament\Resources\PayrollPeriods\Pages\ListPayrollPeriods;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PayrollPeriodResource extends Resource
{
    protected static ?string $model = PayrollPeriod::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payroll Period')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(static::companyOptions())
                            ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                            ->required()
                            ->disabled(fn (): bool => Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin())
                            ->dehydrated()
                            ->searchable()
                            ->preload(),
                        TextInput::make('period_code')
                            ->label('Period Code')
                            ->maxLength(100),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->options(PayrollPeriod::statusLabels())
                            ->default(PayrollPeriod::STATUS_DRAFT)
                            ->required(),
                        DatePicker::make('period_start')
                            ->required(),
                        DatePicker::make('period_end')
                            ->required(),
                        DatePicker::make('pay_date')
                            ->nullable(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period_start', 'desc')
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('period_code')->label('Code')->searchable()->sortable()->toggleable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('period_start')->date()->sortable(),
                TextColumn::make('period_end')->date()->sortable(),
                TextColumn::make('pay_date')->date()->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PayrollPeriod::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => PayrollPeriod::statusColor($state)),
                TextColumn::make('locked_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closed_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('status')
                    ->options(PayrollPeriod::statusLabels()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'lockedBy', 'closedBy']);
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
        return Auth::user() instanceof Employee
            && Gate::forUser(Auth::user())->allows('viewAny', PayrollPeriod::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPeriods::route('/'),
            'create' => CreatePayrollPeriod::route('/create'),
            'edit' => EditPayrollPeriod::route('/{record}/edit'),
        ];
    }

    private static function companyOptions(): \Closure
    {
        return function (): array {
            $user = Auth::user();

            if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                return Company::query()
                    ->whereKey($user->getEffectiveCompanyId())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all();
            }

            return Company::query()->orderBy('name')->pluck('name', 'id')->all();
        };
    }
}
