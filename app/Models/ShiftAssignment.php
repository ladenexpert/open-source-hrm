<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ShiftAssignment extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use SoftDeletes;

    public const ASSIGNABLE_TYPE_EMPLOYEE = 'employee';

    public const ASSIGNABLE_TYPE_DEPARTMENT = 'department';

    public const ASSIGNABLE_TYPE_BRANCH = 'branch';

    protected $fillable = [
        'company_id',
        'assignable_type',
        'assignable_id',
        'shift_pattern_id',
        'effective_date',
        'end_date',
        'work_location_id',
        'assigned_by',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'assignable_id' => 'integer',
        'shift_pattern_id' => 'integer',
        'effective_date' => 'date',
        'end_date' => 'date',
        'work_location_id' => 'integer',
        'assigned_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            $assignment->validateCoreFields();
            $assignment->validateScopedReferences();
            $assignment->validateOverlaps();
        });
    }

    /**
     * @return array<string, string>
     */
    public static function assignableTypeLabels(): array
    {
        return [
            self::ASSIGNABLE_TYPE_EMPLOYEE => 'Employee',
            self::ASSIGNABLE_TYPE_DEPARTMENT => 'Department',
            self::ASSIGNABLE_TYPE_BRANCH => 'Branch',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function scopeActiveOn(Builder $query, Carbon $date): Builder
    {
        $resolvedDate = $date->toDateString();

        return $query
            ->whereDate('effective_date', '<=', $resolvedDate)
            ->where(function (Builder $scopedQuery) use ($resolvedDate): void {
                $scopedQuery
                    ->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $resolvedDate);
            });
    }

    public function scopeForAssignable(Builder $query, string $type, int $id): Builder
    {
        return $query
            ->where('assignable_type', $type)
            ->where('assignable_id', $id);
    }

    public function assignableDisplayName(): string
    {
        $assignable = $this->relationLoaded('assignable')
            ? $this->assignable
            : $this->assignable()->first();

        return match (true) {
            $assignable instanceof Employee => $assignable->full_name,
            $assignable instanceof Department => $assignable->name,
            $assignable instanceof Branch => $assignable->name,
            default => 'Unknown',
        };
    }

    private function validateCoreFields(): void
    {
        if (! array_key_exists($this->assignable_type, self::assignableTypeLabels())) {
            throw ValidationException::withMessages([
                'assignable_type' => 'The selected assignable type is invalid.',
            ]);
        }

        if (blank($this->assignable_id)) {
            throw ValidationException::withMessages([
                'assignable_id' => 'The assignable target is required.',
            ]);
        }

        if (filled($this->end_date) && $this->end_date->lt($this->effective_date)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after the effective date.',
            ]);
        }
    }

    private function validateScopedReferences(): void
    {
        $assignableCompanyId = $this->resolveAssignableCompanyId();
        $shiftPatternCompanyId = ShiftPattern::query()->whereKey($this->shift_pattern_id)->value('company_id');

        $this->company_id ??= $assignableCompanyId ?: $shiftPatternCompanyId;

        if (! filled($assignableCompanyId) || (int) $assignableCompanyId !== (int) $this->company_id) {
            throw ValidationException::withMessages([
                'assignable_id' => 'The selected assignable record must belong to the selected company.',
            ]);
        }

        if (! filled($shiftPatternCompanyId) || (int) $shiftPatternCompanyId !== (int) $this->company_id) {
            throw ValidationException::withMessages([
                'shift_pattern_id' => 'The selected shift pattern must belong to the selected company.',
            ]);
        }

        if (filled($this->work_location_id)) {
            $workLocationCompanyId = WorkLocation::query()->whereKey($this->work_location_id)->value('company_id');

            if (! filled($workLocationCompanyId) || (int) $workLocationCompanyId !== (int) $this->company_id) {
                throw ValidationException::withMessages([
                    'work_location_id' => 'The selected work location must belong to the selected company.',
                ]);
            }
        }

        if (filled($this->assigned_by)) {
            $assignedByCompanyId = Employee::query()->whereKey($this->assigned_by)->value('company_id');

            if (! filled($assignedByCompanyId) || (int) $assignedByCompanyId !== (int) $this->company_id) {
                throw ValidationException::withMessages([
                    'assigned_by' => 'The selected assigning employee must belong to the selected company.',
                ]);
            }
        }
    }

    private function validateOverlaps(): void
    {
        $endDate = $this->end_date?->toDateString() ?? '9999-12-31';

        $overlapExists = static::query()
            ->forCompany($this->company_id)
            ->forAssignable($this->assignable_type, (int) $this->assignable_id)
            ->whereKeyNot($this->getKey())
            ->whereDate('effective_date', '<=', $endDate)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $this->effective_date->toDateString());
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'effective_date' => 'This shift assignment overlaps with an existing active assignment for the same target.',
            ]);
        }
    }

    private function resolveAssignableCompanyId(): ?int
    {
        return match ($this->assignable_type) {
            self::ASSIGNABLE_TYPE_EMPLOYEE => Employee::query()->whereKey($this->assignable_id)->value('company_id'),
            self::ASSIGNABLE_TYPE_DEPARTMENT => Department::query()->whereKey($this->assignable_id)->value('company_id'),
            self::ASSIGNABLE_TYPE_BRANCH => Branch::query()->whereKey($this->assignable_id)->value('company_id'),
            default => null,
        };
    }
}
