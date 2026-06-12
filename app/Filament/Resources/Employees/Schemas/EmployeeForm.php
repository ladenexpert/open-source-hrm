<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\WorkLocation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextArea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Basic Information')
                    ->collapsible()
                    ->schema([

                        Grid::make(2)->schema([
                            TextInput::make('employee_code')
                                ->required()
                                ->maxLength(50)
                                ->label('Employee code')
                                ->placeholder('Enter employee number')
                                ->columnSpan(1),
                            TextInput::make('first_name')
                                ->required(),
                            TextInput::make('last_name')
                                ->required(),
                            DatePicker::make('date_of_birth'),
                            Select::make('gender')
                                ->options(['Male' => 'Male', 'Female' => 'Female']),
                            Select::make('marital_status')
                                ->options([
                                    'Single' => 'Single',
                                    'Married' => 'Married',
                                    'Divorced' => 'Divorced',
                                    'Widowed' => 'Widowed',
                                ]),

                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Contact Information')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->label('Email Address')
                                    ->unique(ignoreRecord: true)
                                    ->copyable(),
                                TextInput::make('phone')->tel()->required()->label('Phone Number')->unique(ignoreRecord: true),
                                TextInput::make('national_id')->required()->unique(ignoreRecord: true)
                                    ->integer(),
                                TextInput::make('kra_pin'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Emergency Contact')
                    ->collapsible()
                    ->schema([

                        Grid::make(2)
                            ->schema([
                                TextInput::make('emergency_contact_name'),
                                TextInput::make('emergency_contact_phone'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Next of Kin')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('next_of_kin_name')
                                    ->label('Name')
                                    ->required(),
                                TextInput::make('next_of_kin_relationship')
                                    ->label('Relationship')
                                    ->required(),
                                TextInput::make('next_of_kin_phone')
                                    ->required()
                                    ->tel()
                                    ->label('Phone'),
                                TextInput::make('next_of_kin_email')
                                    ->label('Email')
                                    ->email(),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Employment Details')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)

                            ->schema([
                                Select::make('company_id')
                                    ->label('Company')
                                    ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->default(fn (): ?int => auth()->user() instanceof Employee ? auth()->user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                                    ->required()
                                    ->searchable()
                                    ->disabled(fn (): bool => auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin())
                                    ->dehydrated(),
                                Select::make('department_id')
                                    ->relationship(
                                        name: 'department',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            $query->select('id', 'name', 'company_id')->orderBy('name', 'asc');

                                            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                                                $query->forCompany(auth()->user()->getEffectiveCompanyId());
                                            }

                                            return $query;
                                        }
                                    )
                                    ->label('Department')
                                    ->searchable()
                                    ->placeholder('Select a department')
                                    ->preload()
                                    ->nullable(),
                                Select::make('branch_id')
                                    ->label('Branch')
                                    ->options(function (): array {
                                        $query = Branch::query()->orderBy('name');

                                        if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                                            $query->forCompany(auth()->user()->getEffectiveCompanyId());
                                        }

                                        return $query->pluck('name', 'id')->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Select::make('work_location_id')
                                    ->label('Work Location')
                                    ->options(function (): array {
                                        $query = WorkLocation::query()->orderBy('name');

                                        if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                                            $query->forCompany(auth()->user()->getEffectiveCompanyId());
                                        }

                                        return $query->pluck('name', 'id')->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Select::make('cost_center_id')
                                    ->label('Cost Center')
                                    ->options(function (): array {
                                        $query = CostCenter::query()->orderBy('name');

                                        if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                                            $query->forCompany(auth()->user()->getEffectiveCompanyId());
                                        }

                                        return $query->pluck('name', 'id')->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Select::make('position_id')
                                    ->options(function (): array {
                                        $query = Position::query()->orderBy('title');

                                        if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                                            $query->forCompany(auth()->user()->getEffectiveCompanyId());
                                        }

                                        return $query->pluck('title', 'id')->all();
                                    })
                                    ->label('Position')
                                    ->searchable()
                                    ->placeholder('Select a position')
                                    ->preload()
                                    ->nullable()
                                    ->createOptionForm([
                                        TextInput::make('title')
                                            ->required()
                                            ->label('Position Title'),
                                        Select::make('department_id')
                                            ->options(function (): array {
                                                $query = Department::query()->orderBy('name');

                                                if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                                                    $query->forCompany(auth()->user()->getEffectiveCompanyId());
                                                }

                                                return $query->pluck('name', 'id')->all();
                                            }),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('code')
                                                    ->label('Position Code')
                                                    ->unique(ignoreRecord: true)
                                                    ->nullable(),
                                                TextInput::make('salary')
                                                    ->label('Salary')
                                                    ->numeric()
                                                    ->nullable(),
                                            ]),
                                        TextArea::make('description')
                                            ->label('Description')
                                            ->nullable()
                                            ->maxLength(255),

                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return Position::create([
                                            'company_id' => auth()->user() instanceof Employee ? auth()->user()->getEffectiveCompanyId() : Company::getDefaultCompanyId(),
                                            'title' => $data['title'],
                                            'department_id' => $data['department_id'],
                                            'code' => $data['code'] ?? null,
                                            'salary' => $data['salary'] ?? null,
                                            'description' => $data['description'] ?? null,
                                        ])->id;
                                    })
                                    ->native(false),
                                Select::make('employment_type')
                                    ->options([
                                        'Permanent' => 'Permanent',
                                        'Contract' => 'Contract',
                                        'Casual' => 'Casual',
                                    ])
                                    ->required(),
                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->minLength(8)
                                    ->same('password_confirmation'),
                                TextInput::make('password_confirmation')
                                    ->label('Confirm Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->minLength(8),
                                DatePicker::make('hire_date')->required(),
                                DatePicker::make('termination_date'),
                                Toggle::make('is_active')->default(true),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
