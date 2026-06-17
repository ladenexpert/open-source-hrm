<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const HALF_DAY_FIRST = 'first_half';

    public const HALF_DAY_SECOND = 'second_half';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'leave_entitlement_id',
        'start_date',
        'end_date',
        'is_half_day',
        'half_day_type',
        'requested_days',
        'reason',
        'status',
        'submitted_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'leave_type_id' => 'integer',
        'leave_entitlement_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_half_day' => 'boolean',
        'requested_days' => 'decimal:2',
        'submitted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancelled_by' => 'integer',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function halfDayTypeOptions(): array
    {
        return [
            self::HALF_DAY_FIRST => 'First Half',
            self::HALF_DAY_SECOND => 'Second Half',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveEntitlement(): BelongsTo
    {
        return $this->belongsTo(LeaveEntitlement::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cancelled_by');
    }

    public function attachment(): HasOne
    {
        return $this->hasOne(LeaveRequestAttachment::class)->latestOfMany('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class)->latest('id');
    }

    public function scopeForEmployee(Builder $query, Employee $employee): Builder
    {
        return $query
            ->forCompany($employee->getEffectiveCompanyId())
            ->where('employee_id', $employee->getKey());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
        ]);
    }

    public function scopeByStatus(Builder $query, string|array $status): Builder
    {
        $statuses = is_array($status) ? $status : [$status];

        return $query->whereIn('status', $statuses);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
        ], true);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    protected static function newFactory(): LeaveRequestFactory
    {
        return LeaveRequestFactory::new();
    }
}
