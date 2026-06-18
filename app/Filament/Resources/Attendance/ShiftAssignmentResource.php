<?php

namespace App\Filament\Resources\Attendance;

use App\Filament\Resources\Attendance\ShiftAssignmentResource\Pages\CreateShiftAssignment;
use App\Filament\Resources\Attendance\ShiftAssignmentResource\Pages\EditShiftAssignment;
use App\Filament\Resources\Attendance\ShiftAssignmentResource\Pages\ListShiftAssignments;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ShiftAssignmentResource extends Resource
{
    protected static ?string $model = ShiftAssignment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shift Assignment')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(static::companyOptions())
                            ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('assignable_type')
                            ->options(ShiftAssignment::assignableTypeLabels())
                            ->required()
                            ->live(),
                        Select::make('assignable_id')
                            ->label(fn ($get): string => ShiftAssignment::assignableTypeLabels()[$get('assignable_type')] ?? 'Assignable')
                            ->options(fn ($get): array => static::assignableOptions(
                                (string) ($get('assignable_type') ?? ''),
                                $get('company_id') ? (int) $get('company_id') : null,
                            ))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('shift_pattern_id')
                            ->label('Shift Pattern')
                            ->options(fn ($get): array => static::shiftPatternOptions($get('company_id') ? (int) $get('company_id') : null))
                            ->required()
                            ->searchable()
                            ->preload(),
                        DatePicker::make('effective_date')
                            ->required(),
                        DatePicker::make('end_date'),
                        Select::make('work_location_id')
                            ->label('Work Location')
                            ->options(fn ($get): array => static::workLocationOptions($get('company_id') ? (int) $get('company_id') : null))
                            ->searchable()
                            ->preload(),
                    ]),
                    Textarea::make('notes')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assignable_type')
                    ->label('Assignable Type')
                    ->formatStateUsing(fn (string $state): string => ShiftAssignment::assignableTypeLabels()[$state] ?? $state),
                TextColumn::make('assignable_name')
                    ->label('Assignable')
                    ->state(fn (ShiftAssignment $record): string => $record->assignableDisplayName()),
                TextColumn::make('shiftPattern.name')->label('Shift Pattern')->sortable()->searchable(),
                TextColumn::make('effective_date')->date()->sortable(),
                TextColumn::make('end_date')
                    ->label('End Date')
                    ->formatStateUsing(fn ($state): string => $state ? $state->toDateString() : 'Open-ended'),
                TextColumn::make('workLocation.name')->label('Work Location')->toggleable(),
                TextColumn::make('company.name')->label('Company')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('assignable_type')
                    ->label('Assignable Type')
                    ->options(ShiftAssignment::assignableTypeLabels()),
                SelectFilter::make('shift_pattern_id')
                    ->label('Shift Pattern')
                    ->options(static::allShiftPatternOptions()),
                Filter::make('active_on')
                    ->schema([
                        DatePicker::make('date')->label('Active On'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['date'] ?? null)) {
                            return $query;
                        }

                        return $query->activeOn(Carbon::parse($data['date']));
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'assignable', 'shiftPattern', 'workLocation']);
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
            && Gate::forUser(Auth::user())->allows('viewAny', ShiftAssignment::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShiftAssignments::route('/'),
            'create' => CreateShiftAssignment::route('/create'),
            'edit' => EditShiftAssignment::route('/{record}/edit'),
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

    private static function assignableOptions(string $type, ?int $companyId): array
    {
        if (blank($companyId)) {
            return [];
        }

        return match ($type) {
            ShiftAssignment::ASSIGNABLE_TYPE_EMPLOYEE => Employee::query()
                ->where('company_id', $companyId)
                ->orderBy('full_name')
                ->pluck('full_name', 'id')
                ->all(),
            ShiftAssignment::ASSIGNABLE_TYPE_DEPARTMENT => Department::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            ShiftAssignment::ASSIGNABLE_TYPE_BRANCH => Branch::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            default => [],
        };
    }

    private static function shiftPatternOptions(?int $companyId): array
    {
        if (blank($companyId)) {
            return [];
        }

        return ShiftPattern::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function allShiftPatternOptions(): array
    {
        $query = ShiftPattern::query()->orderBy('name');
        $user = Auth::user();

        if ($user instanceof Employee && ! $user->isSuperAdmin()) {
            $query->forCompanies($user->accessibleCompanyIds());
        }

        return $query->pluck('name', 'id')->all();
    }

    private static function workLocationOptions(?int $companyId): array
    {
        if (blank($companyId)) {
            return [];
        }

        return WorkLocation::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
