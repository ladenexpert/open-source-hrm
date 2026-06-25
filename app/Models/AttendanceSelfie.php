<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AttendanceSelfie extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'attendance_log_id',
        'employee_id',
        'company_id',
        'image_path',
        'captured_at',
        'device_info',
        'metadata',
    ];

    protected $casts = [
        'attendance_log_id' => 'integer',
        'employee_id' => 'integer',
        'company_id' => 'integer',
        'captured_at' => 'datetime',
        'device_info' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $attendanceSelfie): void {
            $attendanceSelfie->validateCompanyScope();
        });
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->attendance_log_id)) {
            return AttendanceLog::query()->whereKey($this->attendance_log_id)->value('company_id');
        }

        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        return null;
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForEmployee(Builder $query, Employee|int|null $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->getKey() : $employee;

        if (blank($employeeId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('employee_id'), $employeeId);
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;

        $this->assertScopedCompany(AttendanceLog::class, $this->attendance_log_id, 'attendance_log_id', $companyId);
        $this->assertScopedCompany(Employee::class, $this->employee_id, 'employee_id', $companyId);
    }

    private function assertScopedCompany(string $modelClass, ?int $recordId, string $field, ?int $companyId): void
    {
        if (blank($recordId)) {
            return;
        }

        $scopedCompanyId = $modelClass::query()->whereKey($recordId)->value('company_id');

        if (! filled($scopedCompanyId) || (int) $scopedCompanyId !== (int) $companyId) {
            throw ValidationException::withMessages([
                $field => 'The selected record must belong to the selected company.',
            ]);
        }
    }
}
