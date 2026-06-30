<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Company extends Model
{
    use SoftDeletes;

    public const DEFAULT_CODE = 'DEFAULT';

    protected $fillable = [
        'company_group_id',
        'parent_company_id',
        'code',
        'name',
        'legal_name',
        'email',
        'phone',
        'address',
        'tax_id',
        'company_type',
        'is_legal_entity',
        'default_attendance_policy_id',
        'default_shift_pattern_id',
        'is_active',
    ];

    protected $casts = [
        'company_group_id' => 'integer',
        'parent_company_id' => 'integer',
        'default_attendance_policy_id' => 'integer',
        'default_shift_pattern_id' => 'integer',
        'is_legal_entity' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $company): void {
            if (filled($company->default_attendance_policy_id)) {
                $policyCompanyId = AttendancePolicy::query()
                    ->whereKey($company->default_attendance_policy_id)
                    ->value('company_id');

                if (! filled($policyCompanyId) || (filled($company->id) && (int) $policyCompanyId !== (int) $company->id)) {
                    throw ValidationException::withMessages([
                        'default_attendance_policy_id' => 'The default attendance policy must belong to the same company.',
                    ]);
                }
            }

            if (filled($company->default_shift_pattern_id)) {
                $shiftPatternCompanyId = ShiftPattern::query()
                    ->whereKey($company->default_shift_pattern_id)
                    ->value('company_id');

                if (! filled($shiftPatternCompanyId) || (filled($company->id) && (int) $shiftPatternCompanyId !== (int) $company->id)) {
                    throw ValidationException::withMessages([
                        'default_shift_pattern_id' => 'The default shift pattern must belong to the same company.',
                    ]);
                }
            }
        });
    }

    public static function defaultAttributes(): array
    {
        return [
            'code' => self::DEFAULT_CODE,
            'name' => 'Default Holding Company',
            'legal_name' => 'Default Holding Company',
            'company_type' => 'holding',
            'is_legal_entity' => true,
            'is_active' => true,
        ];
    }

    public static function findOrCreateDefault(): self
    {
        $company = static::withTrashed()->firstOrCreate(
            ['code' => self::DEFAULT_CODE],
            self::defaultAttributes()
        );

        if ($company->trashed()) {
            $company->restore();
        }

        return $company;
    }

    public static function getDefaultCompanyId(): ?int
    {
        return static::query()
            ->where('code', self::DEFAULT_CODE)
            ->value('id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function companyGroup(): BelongsTo
    {
        return $this->belongsTo(CompanyGroup::class);
    }

    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_company_id');
    }

    public function subsidiaries(): HasMany
    {
        return $this->hasMany(self::class, 'parent_company_id');
    }

    public function workLocations(): HasMany
    {
        return $this->hasMany(WorkLocation::class);
    }

    public function attendancePolicies(): HasMany
    {
        return $this->hasMany(AttendancePolicy::class);
    }

    public function defaultAttendancePolicy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class, 'default_attendance_policy_id');
    }

    public function shiftPatterns(): HasMany
    {
        return $this->hasMany(ShiftPattern::class);
    }

    public function defaultShiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class, 'default_shift_pattern_id');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function hostedEmployees(): HasMany
    {
        return $this->hasMany(Employee::class, 'host_company_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function overtimeCalculations(): HasMany
    {
        return $this->hasMany(OvertimeCalculation::class);
    }

    public function employeeDevices(): HasMany
    {
        return $this->hasMany(EmployeeDevice::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(LeaveType::class);
    }

    public function leavePolicies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    public function leaveTransactions(): HasMany
    {
        return $this->hasMany(LeaveTransaction::class);
    }

    public function leaveRequestRecords(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveRequestAttachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class);
    }

    public function holidayCalendars(): HasMany
    {
        return $this->hasMany(HolidayCalendar::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function workdayPatterns(): HasMany
    {
        return $this->hasMany(WorkdayPattern::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
