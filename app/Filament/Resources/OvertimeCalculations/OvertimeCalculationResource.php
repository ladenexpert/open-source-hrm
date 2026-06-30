<?php

namespace App\Filament\Resources\OvertimeCalculations;

use App\Filament\Resources\OvertimeCalculations\Pages\ListOvertimeCalculations;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeCalculation;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OvertimeCalculationResource extends Resource
{
    protected static ?string $model = OvertimeCalculation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Overtime Calculation';

    protected static ?string $pluralModelLabel = 'Overtime Calculations';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('calculation_date', 'desc')
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable()->sortable(),
                TextColumn::make('calculation_date')->date()->sortable(),
                TextColumn::make('calculation_status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OvertimeCalculation::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => OvertimeCalculation::statusColor($state)),
                TextColumn::make('scheduled_end_at')->dateTime()->toggleable(),
                TextColumn::make('actual_clock_out_at')->dateTime()->toggleable(),
                TextColumn::make('actual_overtime_minutes')->label('Actual OT')->sortable(),
                TextColumn::make('requested_minutes')->sortable()->toggleable(),
                TextColumn::make('approved_minutes')->sortable()->toggleable(),
                TextColumn::make('calculated_minutes')->label('Calculated OT')->sortable(),
                TextColumn::make('calculated_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->options(static::employeeOptions()),
                SelectFilter::make('calculation_status')
                    ->label('Status')
                    ->options(OvertimeCalculation::statusLabels()),
                Filter::make('calculation_date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['from'] ?? null)) {
                            $query->whereDate('calculation_date', '>=', $data['from']);
                        }

                        if (filled($data['until'] ?? null)) {
                            $query->whereDate('calculation_date', '<=', $data['until']);
                        }

                        return $query;
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'company',
            'employee',
            'overtimeRequest',
            'attendanceSummary',
        ]);
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
            && Auth::user()->canManageHrMasterData();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOvertimeCalculations::route('/'),
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

    private static function employeeOptions(): array
    {
        $query = Employee::query()->orderBy('full_name');
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            $query->whereIn('company_id', $user->accessibleCompanyIds());
        }

        return $query->pluck('full_name', 'id')->all();
    }
}
