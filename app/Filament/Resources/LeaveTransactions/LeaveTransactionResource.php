<?php

namespace App\Filament\Resources\LeaveTransactions;

use App\Filament\Resources\LeaveTransactions\Pages\ListLeaveTransactions;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeaveTransactionResource extends Resource
{
    protected static ?string $model = LeaveTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Leave Transaction';

    protected static ?string $pluralModelLabel = 'Leave Transactions';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Created At')->dateTime()->sortable(),
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LeaveTransaction::typeOptions()[$state] ?? $state),
                TextColumn::make('employee.full_name')->label('Employee')->searchable()->sortable(),
                TextColumn::make('leaveType.name')->label('Leave Type')->searchable()->sortable(),
                TextColumn::make('days')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('balance_before')->label('Before')->numeric(decimalPlaces: 2)->toggleable(),
                TextColumn::make('balance_after')->label('After')->numeric(decimalPlaces: 2)->toggleable(),
                TextColumn::make('reference_type')
                    ->label('Reference')
                    ->formatStateUsing(fn (?string $state, LeaveTransaction $record): string => blank($state)
                        ? '-'
                        : class_basename($state).(filled($record->reference_id) ? " #{$record->reference_id}" : '')),
                TextColumn::make('remarks')->limit(60)->wrap(),
                TextColumn::make('createdBy.full_name')->label('Created By')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('transaction_type')
                    ->label('Transaction Type')
                    ->options(LeaveTransaction::typeOptions()),
                SelectFilter::make('leave_type_id')
                    ->label('Leave Type')
                    ->options(static::leaveTypeOptions()),
                Filter::make('transaction_year')
                    ->label('Year')
                    ->schema([
                        TextInput::make('year')->numeric()->minValue(2000)->maxValue(2100),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['year'] ?? null),
                            fn (Builder $scopedQuery): Builder => $scopedQuery->whereYear('created_at', (int) $data['year']),
                        );
                    }),
                Filter::make('created_between')
                    ->label('Date Range')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $scopedQuery): Builder => $scopedQuery->whereDate('created_at', '>=', $data['from']),
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $scopedQuery): Builder => $scopedQuery->whereDate('created_at', '<=', $data['until']),
                            );
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'employee', 'leaveType', 'createdBy']);
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
            && Gate::forUser(Auth::user())->allows('viewAny', LeaveTransaction::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveTransactions::route('/'),
        ];
    }

    private static function companyOptions(): array
    {
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
        }

        return Company::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    private static function leaveTypeOptions(): array
    {
        return LeaveType::query()
            ->when(
                Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                fn (Builder $query): Builder => $query->forCompanies(Auth::user()->accessibleCompanyIds()),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
