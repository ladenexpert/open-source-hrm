<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\EmployeeFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCompany;

    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_code',
        'full_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'national_id',
        'nik_ktp',
        'npwp_number',
        'passport_number',
        'kitas_kitap_number',
        'bpjs_kesehatan_number',
        'bpjs_ketenagakerjaan_number',
        'kra_pin',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'date_of_birth',
        'gender',
        'marital_status',
        'company_id',
        'company_group_id',
        'branch_id',
        'work_location_id',
        'cost_center_id',
        'bank_id',
        'bank_account_number',
        'bank_account_holder_name',
        'department_id',
        'division_id',
        'position_id',
        'job_level_id',
        'job_grade_id',
        'employment_type',
        'employment_status_id',
        'employment_type_id',
        'contract_type_id',
        'identity_type_id',
        'religion_id',
        'marital_status_id',
        'hire_date',
        'join_date',
        'probation_end_date',
        'contract_start_date',
        'contract_end_date',
        'resign_date',
        'direct_supervisor_id',
        'termination_date',
        'nationality',
        'citizenship_status',
        'expatriate_status',
        'visa_type',
        'visa_number',
        'visa_issued_date',
        'visa_expiry_date',
        'work_permit_number',
        'work_permit_expiry_date',
        'passport_expiry_date',
        'home_country',
        'home_company',
        'host_company_id',
        'assignment_start_date',
        'assignment_end_date',
        'payroll_scheme',
        'tax_residency_status',
        'attendance_policy_id',
        'attendance_location_mode_override',
        'bpjs_eligible',
        'thr_eligible',
        'is_active',
        'next_of_kin_name',
        'next_of_kin_relationship',
        'next_of_kin_phone',
        'next_of_kin_email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'name',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $employee): void {
            $employee->syncDerivedProfileFields();
            $employee->validateScopedReferences();
            $employee->validateOrganizationStructure();
            $employee->validateEmploymentDates();
            $employee->validateExpatriateProfile();
            $employee->validateAttendanceConfiguration();
        });
    }

    public function getNameAttribute(): string
    {
        return $this->full_name ?: trim("{$this->first_name} {$this->last_name}");
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->canAccessAdminPanel(),
            'portal' => true,
            default => false,
        };
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->hasAnyNormalizedRole([
            'super_admin',
            'company_group_admin',
            'company_admin',
            'admin',
            'hr',
            'hr_admin',
            'hr_head',
            'finance',
            'finance_admin',
            'finance_head',
            'department_manager',
            'department_head',
            'leader',
            'company_head',
        ]);
    }

    public function canManageHrMasterData(): bool
    {
        return $this->hasAnyNormalizedRole([
            'super_admin',
            'company_group_admin',
            'company_admin',
            'admin',
            'hr',
            'hr_admin',
            'hr_head',
        ]);
    }

    public function canManagePayroll(): bool
    {
        return $this->hasAnyNormalizedRole([
            'super_admin',
            'finance',
            'finance_admin',
            'finance_head',
        ]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasNormalizedRole('super_admin');
    }

    public function isCompanyGroupAdmin(): bool
    {
        return $this->hasNormalizedRole('company_group_admin');
    }

    public function isCompanyAdmin(): bool
    {
        return $this->hasAnyNormalizedRole([
            'company_admin',
            'admin',
        ]);
    }

    public function isDepartmentManager(): bool
    {
        return $this->hasAnyNormalizedRole([
            'department_manager',
            'department_head',
            'leader',
        ]);
    }

    public function getEffectiveCompanyId(): ?int
    {
        return $this->company_id ?: Company::getDefaultCompanyId();
    }

    public function getEffectiveCompanyGroupId(): ?int
    {
        if (filled($this->company_group_id)) {
            return (int) $this->company_group_id;
        }

        return Company::query()
            ->whereKey($this->getEffectiveCompanyId())
            ->value('company_group_id');
    }

    public function accessibleCompaniesQuery(): Builder
    {
        if ($this->isSuperAdmin()) {
            return Company::query();
        }

        if ($this->hasCompanyGroupWideScope()) {
            $companyGroupId = $this->getEffectiveCompanyGroupId();

            if (filled($companyGroupId)) {
                return Company::query()->where('company_group_id', $companyGroupId);
            }
        }

        return Company::query()->whereKey($this->getEffectiveCompanyId());
    }

    public function accessibleCompanyIds(): array
    {
        return $this->accessibleCompaniesQuery()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function canAccessCompany(?int $companyId): bool
    {
        if (blank($companyId)) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ((int) $companyId === (int) $this->getEffectiveCompanyId()) {
            return true;
        }

        if (! $this->hasCompanyGroupWideScope()) {
            return false;
        }

        $companyGroupId = $this->getEffectiveCompanyGroupId();

        if (blank($companyGroupId)) {
            return false;
        }

        return Company::query()
            ->whereKey($companyId)
            ->where('company_group_id', $companyGroupId)
            ->exists();
    }

    public function canAccessCompanyGroup(?int $companyGroupId): bool
    {
        if (blank($companyGroupId)) {
            return false;
        }

        return $this->isSuperAdmin()
            || (
                $this->hasCompanyGroupWideScope()
                && (int) $this->getEffectiveCompanyGroupId() === (int) $companyGroupId
            );
    }

    public function hasNormalizedRole(string $role): bool
    {
        $expectedRole = $this->normalizeRoleName($role);

        return $this->getRoleNames()
            ->contains(fn (string $assignedRole): bool => $this->normalizeRoleName($assignedRole) === $expectedRole);
    }

    public function hasAnyNormalizedRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasNormalizedRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function managesDepartment(?int $departmentId): bool
    {
        if (! $this->isDepartmentManager() || blank($departmentId)) {
            return false;
        }

        return $this->managedDepartments()
            ->forCompany($this->getEffectiveCompanyId())
            ->whereKey($departmentId)
            ->exists();
    }

    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    public function companyGroup(): BelongsTo
    {
        return $this->belongsTo(CompanyGroup::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function attendancePolicy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class);
    }

    public function jobGrade(): BelongsTo
    {
        return $this->belongsTo(JobGrade::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function identityType(): BelongsTo
    {
        return $this->belongsTo(IdentityType::class);
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class);
    }

    public function maritalStatusMaster(): BelongsTo
    {
        return $this->belongsTo(MaritalStatus::class, 'marital_status_id');
    }

    public function directSupervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'direct_supervisor_id');
    }

    public function supervisees(): HasMany
    {
        return $this->hasMany(self::class, 'direct_supervisor_id');
    }

    public function hostCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'host_company_id');
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    public function shiftAssignments(): MorphMany
    {
        return $this->morphMany(ShiftAssignment::class, 'assignable');
    }

    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function leaveTransactions(): HasMany
    {
        return $this->hasMany(LeaveTransaction::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function cancelledLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'cancelled_by');
    }

    public function leaveRequestAttachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class, 'uploaded_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'join_date' => 'date',
            'probation_end_date' => 'date',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'resign_date' => 'date',
            'termination_date' => 'date',
            'company_id' => 'integer',
            'company_group_id' => 'integer',
            'branch_id' => 'integer',
            'work_location_id' => 'integer',
            'cost_center_id' => 'integer',
            'bank_id' => 'integer',
            'department_id' => 'integer',
            'division_id' => 'integer',
            'position_id' => 'integer',
            'job_level_id' => 'integer',
            'job_grade_id' => 'integer',
            'employment_status_id' => 'integer',
            'employment_type_id' => 'integer',
            'contract_type_id' => 'integer',
            'identity_type_id' => 'integer',
            'religion_id' => 'integer',
            'marital_status_id' => 'integer',
            'direct_supervisor_id' => 'integer',
            'host_company_id' => 'integer',
            'visa_issued_date' => 'date',
            'visa_expiry_date' => 'date',
            'work_permit_expiry_date' => 'date',
            'passport_expiry_date' => 'date',
            'assignment_start_date' => 'date',
            'assignment_end_date' => 'date',
            'attendance_policy_id' => 'integer',
            'bpjs_eligible' => 'boolean',
            'thr_eligible' => 'boolean',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected function normalizeRoleName(string $role): string
    {
        return Str::of($role)
            ->lower()
            ->replace(['-', ' '], '_')
            ->value();
    }

    public function hasCompanyGroupWideScope(): bool
    {
        return $this->isCompanyGroupAdmin()
            || $this->hasAnyNormalizedRole([
                'hr_head',
                'finance_head',
                'company_head',
            ]);
    }

    private function syncDerivedProfileFields(): void
    {
        $fullName = trim((string) ($this->full_name ?: trim("{$this->first_name} {$this->last_name}")));

        if ($fullName !== '') {
            $this->full_name = $fullName;
        }

        if (blank($this->first_name) || blank($this->last_name)) {
            $nameParts = preg_split('/\s+/', $fullName) ?: [];

            $this->first_name = $this->first_name ?: ($nameParts[0] ?? 'Employee');
            $this->last_name = $this->last_name ?: trim(implode(' ', array_slice($nameParts, 1))) ?: '-';
        }

        if (blank($this->employee_code)) {
            $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
            $this->employee_code = sprintf('EMP-%05d', $nextId);
        }

        if (blank($this->company_id)) {
            $this->company_id = Company::getDefaultCompanyId();
        }

        $this->company_group_id = Company::query()
            ->whereKey($this->company_id)
            ->value('company_group_id');

        $this->join_date ??= $this->hire_date;
        $this->hire_date ??= $this->join_date;
        $this->national_id ??= $this->nik_ktp;

        if (filled($this->employment_type_id)) {
            $employmentTypeCode = EmploymentType::query()
                ->whereKey($this->employment_type_id)
                ->value('code');

            $this->employment_type = match ($employmentTypeCode) {
                'PKWTT' => 'Permanent',
                'DAILY_WORKER' => 'Casual',
                default => 'Contract',
            };
        }

        if (filled($this->marital_status_id)) {
            $this->marital_status = MaritalStatus::query()
                ->whereKey($this->marital_status_id)
                ->value('name') ?: $this->marital_status;
        }
    }

    private function validateScopedReferences(): void
    {
        $companyId = $this->getEffectiveCompanyId();
        $companyGroupId = $this->getEffectiveCompanyGroupId();

        $this->validateMasterDataReference(Bank::class, $this->bank_id, 'bank_id', $companyId, $companyGroupId);
        $this->validateMasterDataReference(JobLevel::class, $this->job_level_id, 'job_level_id', $companyId, $companyGroupId);
        $this->validateMasterDataReference(JobGrade::class, $this->job_grade_id, 'job_grade_id', $companyId, $companyGroupId);
        $this->validateMasterDataReference(EmploymentStatus::class, $this->employment_status_id, 'employment_status_id', $companyId, $companyGroupId);
        $this->validateMasterDataReference(EmploymentType::class, $this->employment_type_id, 'employment_type_id', $companyId, $companyGroupId);
        $this->validateMasterDataReference(ContractType::class, $this->contract_type_id, 'contract_type_id', $companyId, $companyGroupId);
        $this->validateMasterDataReference(IdentityType::class, $this->identity_type_id, 'identity_type_id', $companyId, $companyGroupId);
        $this->validateMasterDataReference(Religion::class, $this->religion_id, 'religion_id', $companyId, $companyGroupId);
        $this->validateMasterDataReference(MaritalStatus::class, $this->marital_status_id, 'marital_status_id', $companyId, $companyGroupId);
    }

    private function validateOrganizationStructure(): void
    {
        $companyId = $this->getEffectiveCompanyId();
        $companyGroupId = $this->getEffectiveCompanyGroupId();

        if (filled($this->branch_id)) {
            $branchCompanyId = Branch::query()->whereKey($this->branch_id)->value('company_id');

            if (filled($branchCompanyId) && (int) $branchCompanyId !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'The selected branch must belong to the selected company.',
                ]);
            }
        }

        if (filled($this->work_location_id)) {
            $workLocation = WorkLocation::query()->find($this->work_location_id);

            if (! $workLocation || (int) $workLocation->company_id !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'work_location_id' => 'The selected work location must belong to the selected company.',
                ]);
            }

            if (filled($this->branch_id) && filled($workLocation->branch_id) && (int) $workLocation->branch_id !== (int) $this->branch_id) {
                throw ValidationException::withMessages([
                    'work_location_id' => 'The selected work location must belong to the selected branch.',
                ]);
            }
        }

        if (filled($this->department_id)) {
            $department = Department::query()->find($this->department_id);

            if (! $department || (int) $department->company_id !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'department_id' => 'The selected department must belong to the selected company.',
                ]);
            }

            if (filled($this->branch_id) && filled($department->branch_id) && (int) $department->branch_id !== (int) $this->branch_id) {
                throw ValidationException::withMessages([
                    'department_id' => 'The selected department must belong to the selected branch.',
                ]);
            }
        }

        if (filled($this->division_id)) {
            $division = Division::query()->find($this->division_id);

            if (! $division || (int) $division->company_id !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'division_id' => 'The selected division must belong to the selected company.',
                ]);
            }

            if (filled($this->department_id) && filled($division->department_id) && (int) $division->department_id !== (int) $this->department_id) {
                throw ValidationException::withMessages([
                    'division_id' => 'The selected division must belong to the selected department.',
                ]);
            }
        }

        if (filled($this->position_id)) {
            $position = Position::query()->find($this->position_id);

            if (! $position || (int) $position->company_id !== (int) $companyId) {
                throw ValidationException::withMessages([
                    'position_id' => 'The selected position must belong to the selected company.',
                ]);
            }

            if (filled($this->department_id) && filled($position->department_id) && (int) $position->department_id !== (int) $this->department_id) {
                throw ValidationException::withMessages([
                    'position_id' => 'The selected position must belong to the selected department.',
                ]);
            }

            if (filled($this->division_id) && filled($position->division_id) && (int) $position->division_id !== (int) $this->division_id) {
                throw ValidationException::withMessages([
                    'position_id' => 'The selected position must belong to the selected division.',
                ]);
            }
        }

        if (filled($this->direct_supervisor_id)) {
            if (filled($this->id) && (int) $this->direct_supervisor_id === (int) $this->id) {
                throw ValidationException::withMessages([
                    'direct_supervisor_id' => 'An employee cannot supervise themselves.',
                ]);
            }

            $supervisor = self::query()->find($this->direct_supervisor_id);

            if (! $supervisor) {
                throw ValidationException::withMessages([
                    'direct_supervisor_id' => 'The selected supervisor is invalid.',
                ]);
            }

            $sameCompany = (int) $supervisor->company_id === (int) $companyId;
            $sameGroup = filled($companyGroupId)
                && (int) $supervisor->getEffectiveCompanyGroupId() === (int) $companyGroupId;

            if (! $sameCompany && ! $sameGroup) {
                throw ValidationException::withMessages([
                    'direct_supervisor_id' => 'The selected supervisor must belong to the same company or company group.',
                ]);
            }
        }

        if (filled($this->host_company_id)) {
            $hostCompany = Company::query()->find($this->host_company_id);

            if (! $hostCompany) {
                throw ValidationException::withMessages([
                    'host_company_id' => 'The selected host company is invalid.',
                ]);
            }

            if (filled($companyGroupId) && (int) $hostCompany->company_group_id !== (int) $companyGroupId) {
                throw ValidationException::withMessages([
                    'host_company_id' => 'The selected host company must belong to the same company group.',
                ]);
            }
        }
    }

    private function validateEmploymentDates(): void
    {
        if (filled($this->assignment_start_date) && filled($this->assignment_end_date) && $this->assignment_end_date->lt($this->assignment_start_date)) {
            throw ValidationException::withMessages([
                'assignment_end_date' => 'Assignment end date must be after the assignment start date.',
            ]);
        }

        if (filled($this->visa_issued_date) && filled($this->visa_expiry_date) && $this->visa_expiry_date->lt($this->visa_issued_date)) {
            throw ValidationException::withMessages([
                'visa_expiry_date' => 'Visa expiry date must be after the visa issued date.',
            ]);
        }

        if (filled($this->contract_start_date) && filled($this->contract_end_date) && $this->contract_end_date->lt($this->contract_start_date)) {
            throw ValidationException::withMessages([
                'contract_end_date' => 'Contract end date must be after the contract start date.',
            ]);
        }
    }

    private function validateExpatriateProfile(): void
    {
        if (! $this->isForeignWorker()) {
            return;
        }

        if (blank($this->passport_number) && blank($this->kitas_kitap_number)) {
            throw ValidationException::withMessages([
                'passport_number' => 'Expatriate or foreign workers must have passport or KITAS/KITAP details.',
            ]);
        }
    }

    private function isForeignWorker(): bool
    {
        $employmentTypeCode = EmploymentType::query()->whereKey($this->employment_type_id)->value('code');
        $normalizedNationality = Str::of((string) $this->nationality)->lower()->trim()->value();
        $normalizedCitizenship = Str::of((string) $this->citizenship_status)->lower()->trim()->value();
        $normalizedExpatriateStatus = Str::of((string) $this->expatriate_status)->lower()->trim()->value();

        return in_array($employmentTypeCode, ['EXPATRIATE', 'EXPATRIATE_ASSIGNMENT'], true)
            || in_array($normalizedExpatriateStatus, ['expatriate', 'foreign_worker', 'international_assignment'], true)
            || in_array($normalizedCitizenship, ['foreign_national', 'non_indonesian'], true)
            || ($normalizedNationality !== '' && ! in_array($normalizedNationality, ['indonesia', 'indonesian'], true));
    }

    private function validateMasterDataReference(string $modelClass, ?int $recordId, string $field, ?int $companyId, ?int $companyGroupId): void
    {
        if (blank($recordId)) {
            return;
        }

        /** @var \App\Models\MasterData|null $record */
        $record = $modelClass::query()->find($recordId);

        if (! $record || ! $record->isAvailableFor($companyId, $companyGroupId)) {
            throw ValidationException::withMessages([
                $field => 'The selected value is outside the allowed company or company group scope.',
            ]);
        }
    }

    private function validateAttendanceConfiguration(): void
    {
        if (filled($this->attendance_policy_id)) {
            $policyCompanyId = AttendancePolicy::query()->whereKey($this->attendance_policy_id)->value('company_id');

            if (! filled($policyCompanyId) || (int) $policyCompanyId !== (int) $this->getEffectiveCompanyId()) {
                throw ValidationException::withMessages([
                    'attendance_policy_id' => 'The selected attendance policy must belong to the selected company.',
                ]);
            }
        }

        if (filled($this->attendance_location_mode_override) && ! in_array($this->attendance_location_mode_override, AttendancePolicy::locationModeOptions(), true)) {
            throw ValidationException::withMessages([
                'attendance_location_mode_override' => 'The selected attendance location mode override is invalid.',
            ]);
        }
    }
}
