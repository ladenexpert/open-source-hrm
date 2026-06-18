<?php

namespace App\Filament\Resources\Attendance;

use App\Filament\Resources\Attendance\AttendancePolicyResource\Pages\CreateAttendancePolicy;
use App\Filament\Resources\Attendance\AttendancePolicyResource\Pages\EditAttendancePolicy;
use App\Filament\Resources\Attendance\AttendancePolicyResource\Pages\ListAttendancePolicies;
use App\Models\AttendancePolicy;
use App\Models\Company;
use App\Models\Employee;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\Gate;

class AttendancePolicyResource extends Resource
{
    protected static ?string $model = AttendancePolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Attendance Policy')
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
                        Select::make('location_mode')
                            ->options(AttendancePolicy::locationModeLabels())
                            ->default(AttendancePolicy::LOCATION_MODE_FIXED)
                            ->required()
                            ->searchable(),
                        Toggle::make('gps_required')->default(false),
                        Toggle::make('selfie_required')->default(false),
                        Toggle::make('radius_validation_enabled')
                            ->label('Enable Radius Validation')
                            ->default(false)
                            ->live(),
                        TextInput::make('radius_meters')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn ($get): bool => (bool) $get('radius_validation_enabled')),
                        TextInput::make('late_tolerance_minutes')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('early_out_tolerance_minutes')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('minimum_work_minutes')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('auto_absent_after_minutes')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('overtime_threshold_minutes')
                            ->numeric()
                            ->minValue(0),
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
                TextColumn::make('location_mode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendancePolicy::locationModeLabels()[$state] ?? $state),
                IconColumn::make('gps_required')->label('GPS Required')->boolean(),
                IconColumn::make('selfie_required')->label('Selfie Required')->boolean(),
                TextColumn::make('late_tolerance_minutes')->label('Late Tolerance')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->options(static::companyOptions()),
                SelectFilter::make('location_mode')
                    ->label('Location Mode')
                    ->options(AttendancePolicy::locationModeLabels()),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('company');
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
            && Gate::forUser(Auth::user())->allows('viewAny', AttendancePolicy::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendancePolicies::route('/'),
            'create' => CreateAttendancePolicy::route('/create'),
            'edit' => EditAttendancePolicy::route('/{record}/edit'),
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
