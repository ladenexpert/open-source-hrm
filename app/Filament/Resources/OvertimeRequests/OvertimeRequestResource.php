<?php

namespace App\Filament\Resources\OvertimeRequests;

use App\Filament\Resources\OvertimeRequests\Pages\CreateOvertimeRequest;
use App\Filament\Resources\OvertimeRequests\Pages\ListOvertimeRequests;
use App\Filament\Resources\OvertimeRequests\Pages\ViewOvertimeRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OvertimeRequestResource extends Resource
{
    protected static ?string $model = OvertimeRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Overtime Request';

    protected static ?string $pluralModelLabel = 'Overtime Requests';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->label('Employee')
                ->options(static::employeeOptions())
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('overtime_date')
                ->required(),
            DateTimePicker::make('requested_start_at')
                ->seconds(false)
                ->nullable(),
            DateTimePicker::make('requested_end_at')
                ->seconds(false)
                ->nullable(),
            TextInput::make('requested_minutes')
                ->numeric()
                ->minValue(0)
                ->nullable(),
            Textarea::make('reason')
                ->rows(4)
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('overtime_date', 'desc')
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable()->sortable(),
                TextColumn::make('overtime_date')->date()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OvertimeRequest::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => static::statusColor($state)),
                TextColumn::make('attendanceSummary.status')
                    ->label('Attendance')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('requested_minutes')->sortable()->toggleable(),
                TextColumn::make('approved_minutes')->sortable()->toggleable(),
                TextColumn::make('calculation.actual_overtime_minutes')
                    ->label('Actual OT')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('calculation.calculated_minutes')
                    ->label('Calculated OT')
                    ->sortable(),
                TextColumn::make('submitted_at')->dateTime()->toggleable(),
                TextColumn::make('approved_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->options(static::employeeOptions()),
                SelectFilter::make('status')
                    ->options(OvertimeRequest::statusLabels()),
                Filter::make('overtime_date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['from'] ?? null)) {
                            $query->whereDate('overtime_date', '>=', $data['from']);
                        }

                        if (filled($data['until'] ?? null)) {
                            $query->whereDate('overtime_date', '<=', $data['until']);
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
            'attendanceSummary.attendancePolicy',
            'approvalRequest',
            'calculation',
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
            'index' => ListOvertimeRequests::route('/'),
            'create' => CreateOvertimeRequest::route('/create'),
            'view' => ViewOvertimeRequest::route('/{record}'),
        ];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            OvertimeRequest::STATUS_DRAFT => 'gray',
            OvertimeRequest::STATUS_SUBMITTED => 'warning',
            OvertimeRequest::STATUS_APPROVED => 'success',
            OvertimeRequest::STATUS_REJECTED => 'danger',
            OvertimeRequest::STATUS_CANCELLED => 'secondary',
            default => 'gray',
        };
    }

    private static function companyOptions(): array
    {
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
        }

        return Company::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function employeeOptions(): array
    {
        $query = Employee::query()->orderBy('full_name');
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            $query->whereIn('company_id', $user->accessibleCompanyIds());
        }

        return $query->pluck('full_name', 'id')->all();
    }
}
