<?php

namespace App\Filament\Resources\Positions;

use App\Filament\Resources\Positions\Pages\CreatePosition;
use App\Filament\Resources\Positions\Pages\EditPosition;
use App\Filament\Resources\Positions\Pages\ListPositions;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\JobGrade;
use App\Models\JobLevel;
use App\Models\Position;
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

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')
                ->label('Company')
                ->options(function (): array {
                    $user = Auth::user();

                    if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                        return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
                    }

                    return Company::query()->orderBy('name')->pluck('name', 'id')->all();
                })
                ->default(fn (): ?int => Auth::user() instanceof Employee ? Auth::user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                ->required()
                ->searchable()
                ->preload(),
            Select::make('branch_id')
                ->label('Branch')
                ->options(function (): array {
                    $query = Branch::query()->orderBy('name');

                    if (Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin()) {
                        $query->forCompanies(Auth::user()->accessibleCompanyIds());
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->preload(),
            Select::make('department_id')
                ->label('Department')
                ->options(function (): array {
                    $query = Department::query()->orderBy('name');

                    if (Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin()) {
                        $query->forCompanies(Auth::user()->accessibleCompanyIds());
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->preload(),
            Select::make('division_id')
                ->label('Division')
                ->options(function (): array {
                    $query = Division::query()->orderBy('name');

                    if (Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin()) {
                        $query->visibleTo(Auth::user());
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->preload(),
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('code')->maxLength(50)->unique(ignoreRecord: true),
            Select::make('job_level_id')
                ->label('Job Level')
                ->options(function (): array {
                    $query = JobLevel::query()->orderBy('sort_order')->orderBy('name');

                    if (Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin()) {
                        $query->visibleTo(Auth::user());
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->preload(),
            Select::make('job_grade_id')
                ->label('Job Grade')
                ->options(function (): array {
                    $query = JobGrade::query()->orderBy('sort_order')->orderBy('name');

                    if (Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin()) {
                        $query->visibleTo(Auth::user());
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->preload(),
            TextInput::make('salary')->numeric(),
            Textarea::make('description')->columnSpanFull(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->sortable(),
            TextColumn::make('code')->searchable()->sortable(),
            TextColumn::make('company.name')->label('Company')->toggleable(),
            TextColumn::make('department.name')->label('Department')->toggleable(),
            TextColumn::make('division.name')->label('Division')->toggleable(),
            TextColumn::make('jobLevel.name')->label('Job Level')->toggleable(),
            TextColumn::make('jobGrade.name')->label('Job Grade')->toggleable(),
            IconColumn::make('is_active')->boolean(),
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

        if ($user->canManageHrMasterData()) {
            return $query->forCompanies($user->accessibleCompanyIds());
        }

        if ($user->isDepartmentManager()) {
            return $query
                ->forCompany($user->getEffectiveCompanyId())
                ->whereIn('department_id', $user->managedDepartments()->select('id'));
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof Employee
            && (Auth::user()->canManageHrMasterData() || Auth::user()->isDepartmentManager());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPositions::route('/'),
            'create' => CreatePosition::route('/create'),
            'edit' => EditPosition::route('/{record}/edit'),
        ];
    }
}
