<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class EmployeeDevice extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_TRUSTED = 'trusted';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'employee_id',
        'device_uuid',
        'device_name',
        'platform',
        'browser',
        'user_agent',
        'status',
        'status_reason',
        'first_seen_at',
        'last_used_at',
        'trusted_by',
        'trusted_at',
        'revoked_by',
        'revoked_at',
        'last_ip_address',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'first_seen_at' => 'datetime',
        'last_used_at' => 'datetime',
        'trusted_by' => 'integer',
        'trusted_at' => 'datetime',
        'revoked_by' => 'integer',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $device): void {
            if (! in_array($device->status, self::statusOptions(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected employee device status is invalid.',
                ]);
            }

            $device->device_uuid = trim((string) $device->device_uuid);

            if ($device->device_uuid === '') {
                throw ValidationException::withMessages([
                    'device_uuid' => 'The device UUID is required.',
                ]);
            }

            $device->validateCompanyScope();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_TRUSTED,
            self::STATUS_REVOKED,
            self::STATUS_INACTIVE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_TRUSTED => 'Trusted',
            self::STATUS_REVOKED => 'Revoked',
            self::STATUS_INACTIVE => 'Inactive',
        ];
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        return null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function trustedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'trusted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'revoked_by');
    }

    public function scopeForEmployee(Builder $query, Employee|int|null $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->getKey() : $employee;

        if (blank($employeeId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('employee_id'), $employeeId);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where($this->qualifyColumn('status'), $status);
    }

    public function canBeUsedForAttendance(): bool
    {
        return $this->status === self::STATUS_TRUSTED
            && blank($this->revoked_at);
    }

    private function validateCompanyScope(): void
    {
        $companyId = $this->company_id;
        $employeeCompanyId = Employee::query()->whereKey($this->employee_id)->value('company_id');

        if (filled($employeeCompanyId) && (int) $employeeCompanyId !== (int) $companyId) {
            throw ValidationException::withMessages([
                'employee_id' => 'The selected employee must belong to the selected company.',
            ]);
        }
    }
}
