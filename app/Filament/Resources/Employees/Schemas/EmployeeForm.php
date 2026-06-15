<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\ContractType;
use App\Models\IdentityType;
use App\Models\JobGrade;
use App\Models\JobLevel;
use App\Models\MaritalStatus;
use App\Models\Position;
use App\Models\Religion;
use App\Models\WorkLocation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Indonesian Profile')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('employee_code')
                            ->label('Employee Code')
                            ->maxLength(50),
                        TextInput::make('full_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->tel()
                            ->unique(ignoreRecord: true),
                        Select::make('identity_type_id')
                            ->label('Identity Type')
                            ->options(self::masterDataOptions(IdentityType::class))
                            ->searchable()
                            ->preload(),
                        TextInput::make('nik_ktp')
                            ->label('NIK / KTP')
                            ->maxLength(255),
                        TextInput::make('national_id')
                            ->label('Legacy National ID')
                            ->maxLength(255),
                        TextInput::make('passport_number')
                            ->maxLength(255),
                        TextInput::make('kitas_kitap_number')
                            ->label('KITAS / KITAP Number')
                            ->maxLength(255),
                        DatePicker::make('date_of_birth'),
                        Select::make('gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                            ]),
                        Select::make('religion_id')
                            ->label('Religion')
                            ->options(self::masterDataOptions(Religion::class))
                            ->searchable()
                            ->preload(),
                        Select::make('marital_status_id')
                            ->label('Marital Status')
                            ->options(self::masterDataOptions(MaritalStatus::class))
                            ->searchable()
                            ->preload(),
                        TextInput::make('nationality')
                            ->default('Indonesia')
                            ->maxLength(255),
                        TextInput::make('citizenship_status')
                            ->maxLength(255),
                    ]),
                ]),

            Section::make('Employment Classification')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('employment_status_id')
                            ->label('Employment Status')
                            ->options(self::masterDataOptions(EmploymentStatus::class))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('employment_type_id')
                            ->label('Employment Type')
                            ->options(self::masterDataOptions(EmploymentType::class))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('contract_type_id')
                            ->label('Contract Type')
                            ->options(self::masterDataOptions(ContractType::class))
                            ->searchable()
                            ->preload(),
                        DatePicker::make('join_date'),
                        DatePicker::make('hire_date'),
                        DatePicker::make('probation_end_date'),
                        DatePicker::make('contract_start_date'),
                        DatePicker::make('contract_end_date'),
                        DatePicker::make('resign_date'),
                        DatePicker::make('termination_date'),
                        Toggle::make('is_active')->default(true),
                    ]),
                ]),

            Section::make('Organization')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('company_id')
                            ->label('Legal Company')
                            ->options(self::companyOptions())
                            ->default(fn (): ?int => auth()->user() instanceof Employee ? auth()->user()->getEffectiveCompanyId() : Company::getDefaultCompanyId())
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(self::branchOptions())
                            ->searchable()
                            ->preload(),
                        Select::make('work_location_id')
                            ->label('Work Location')
                            ->options(self::workLocationOptions())
                            ->searchable()
                            ->preload(),
                        Select::make('cost_center_id')
                            ->label('Cost Center')
                            ->options(self::costCenterOptions())
                            ->searchable()
                            ->preload(),
                        Select::make('department_id')
                            ->label('Department')
                            ->options(self::departmentOptions())
                            ->searchable()
                            ->preload(),
                        Select::make('division_id')
                            ->label('Division')
                            ->options(self::divisionOptions())
                            ->searchable()
                            ->preload(),
                        Select::make('position_id')
                            ->label('Position')
                            ->options(self::positionOptions())
                            ->searchable()
                            ->preload(),
                        Select::make('job_level_id')
                            ->label('Job Level')
                            ->options(self::masterDataOptions(JobLevel::class))
                            ->searchable()
                            ->preload(),
                        Select::make('job_grade_id')
                            ->label('Job Grade')
                            ->options(self::masterDataOptions(JobGrade::class))
                            ->searchable()
                            ->preload(),
                        Select::make('direct_supervisor_id')
                            ->label('Direct Supervisor')
                            ->options(self::employeeOptions())
                            ->searchable()
                            ->preload(),
                    ]),
                ]),

            Section::make('Bank & Tax')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('bank_id')
                            ->label('Bank')
                            ->options(self::masterDataOptions(Bank::class))
                            ->searchable()
                            ->preload(),
                        TextInput::make('bank_account_number')
                            ->maxLength(255),
                        TextInput::make('bank_account_holder_name')
                            ->maxLength(255),
                        TextInput::make('npwp_number')
                            ->label('NPWP Number')
                            ->maxLength(255),
                        TextInput::make('kra_pin')
                            ->label('Legacy KRA PIN')
                            ->maxLength(255),
                        TextInput::make('tax_residency_status')
                            ->maxLength(255),
                    ]),
                ]),

            Section::make('BPJS')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('bpjs_kesehatan_number')
                            ->label('BPJS Kesehatan Number')
                            ->maxLength(255),
                        TextInput::make('bpjs_ketenagakerjaan_number')
                            ->label('BPJS Ketenagakerjaan Number')
                            ->maxLength(255),
                        Toggle::make('bpjs_eligible')->default(true),
                        Toggle::make('thr_eligible')->default(true),
                    ]),
                ]),

            Section::make('Emergency Contact')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('emergency_contact_name')
                            ->maxLength(255),
                        TextInput::make('emergency_contact_phone')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('emergency_contact_relation')
                            ->maxLength(255),
                        TextInput::make('next_of_kin_name')
                            ->label('Next of Kin Name')
                            ->maxLength(255),
                        TextInput::make('next_of_kin_relationship')
                            ->maxLength(255),
                        TextInput::make('next_of_kin_phone')
                            ->tel()
                            ->maxLength(255),
                    ]),
                ]),

            Section::make('Expatriate / International Assignment')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('expatriate_status')
                            ->maxLength(255),
                        TextInput::make('visa_type')
                            ->maxLength(255),
                        TextInput::make('visa_number')
                            ->maxLength(255),
                        DatePicker::make('visa_issued_date'),
                        DatePicker::make('visa_expiry_date'),
                        TextInput::make('work_permit_number')
                            ->maxLength(255),
                        DatePicker::make('work_permit_expiry_date'),
                        DatePicker::make('passport_expiry_date'),
                        TextInput::make('home_country')
                            ->maxLength(255),
                        TextInput::make('home_company')
                            ->maxLength(255),
                        Select::make('host_company_id')
                            ->label('Host Company')
                            ->options(self::companyOptions())
                            ->searchable()
                            ->preload(),
                        DatePicker::make('assignment_start_date'),
                        DatePicker::make('assignment_end_date'),
                        TextInput::make('payroll_scheme')
                            ->maxLength(255),
                    ]),
                ]),

            Section::make('Access & Lifecycle')
                ->schema([
                    Grid::make(2)->schema([
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
                    ]),
                ]),
        ]);
    }

    private static function companyOptions(): \Closure
    {
        return function (): array {
            $user = auth()->user();

            if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                return $user->accessibleCompaniesQuery()->orderBy('name')->pluck('name', 'id')->all();
            }

            return Company::query()->orderBy('name')->pluck('name', 'id')->all();
        };
    }

    private static function branchOptions(): \Closure
    {
        return function (): array {
            $query = Branch::query()->orderBy('name');

            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                $query->forCompanies(auth()->user()->accessibleCompanyIds());
            }

            return $query->pluck('name', 'id')->all();
        };
    }

    private static function workLocationOptions(): \Closure
    {
        return function (): array {
            $query = WorkLocation::query()->orderBy('name');

            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                $query->forCompanies(auth()->user()->accessibleCompanyIds());
            }

            return $query->pluck('name', 'id')->all();
        };
    }

    private static function costCenterOptions(): \Closure
    {
        return function (): array {
            $query = CostCenter::query()->orderBy('name');

            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                $query->forCompanies(auth()->user()->accessibleCompanyIds());
            }

            return $query->pluck('name', 'id')->all();
        };
    }

    private static function departmentOptions(): \Closure
    {
        return function (): array {
            $query = Department::query()->orderBy('name');

            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                $query->forCompanies(auth()->user()->accessibleCompanyIds());
            }

            return $query->pluck('name', 'id')->all();
        };
    }

    private static function divisionOptions(): \Closure
    {
        return function (): array {
            $query = Division::query()->orderBy('name');

            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                $query->visibleTo(auth()->user());
            }

            return $query->pluck('name', 'id')->all();
        };
    }

    private static function positionOptions(): \Closure
    {
        return function (): array {
            $query = Position::query()->orderBy('title');

            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                $query->forCompanies(auth()->user()->accessibleCompanyIds());
            }

            return $query->pluck('title', 'id')->all();
        };
    }

    private static function employeeOptions(): \Closure
    {
        return function (): array {
            $query = Employee::query()->orderBy('full_name');

            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                $query->forCompanies(auth()->user()->accessibleCompanyIds());
            }

            return $query->get()->mapWithKeys(
                fn (Employee $employee): array => [$employee->id => $employee->name],
            )->all();
        };
    }

    private static function masterDataOptions(string $modelClass): \Closure
    {
        return function () use ($modelClass): array {
            $query = $modelClass::query()->orderBy('sort_order')->orderBy('name');

            if (auth()->user() instanceof Employee && ! auth()->user()->isSuperAdmin()) {
                $query->visibleTo(auth()->user());
            }

            return $query->pluck('name', 'id')->all();
        };
    }
}
