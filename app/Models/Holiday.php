<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Holiday extends Model
{
    use BelongsToCompany;

    public const TYPE_NATIONAL = 'national';

    public const TYPE_COMPANY = 'company';

    public const TYPE_COLLECTIVE_LEAVE = 'collective_leave';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'company_id',
        'holiday_calendar_id',
        'date',
        'name',
        'type',
        'is_paid',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'holiday_calendar_id' => 'integer',
        'date' => 'date',
        'is_paid' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $holiday): void {
            $holiday->company_id ??= HolidayCalendar::query()
                ->whereKey($holiday->holiday_calendar_id)
                ->value('company_id');

            $holiday->validateCalendarScope();
            $holiday->validateType();
        });
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_NATIONAL => 'National',
            self::TYPE_COMPANY => 'Company',
            self::TYPE_COLLECTIVE_LEAVE => 'Collective Leave',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public function holidayCalendar(): BelongsTo
    {
        return $this->belongsTo(HolidayCalendar::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('holidayCalendar', fn (Builder $calendarQuery): Builder => $calendarQuery->where('is_active', true));
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->holiday_calendar_id)) {
            return HolidayCalendar::query()->whereKey($this->holiday_calendar_id)->value('company_id');
        }

        return null;
    }

    private function validateCalendarScope(): void
    {
        $calendarCompanyId = HolidayCalendar::query()->whereKey($this->holiday_calendar_id)->value('company_id');

        if (! filled($calendarCompanyId) || (int) $calendarCompanyId !== (int) $this->company_id) {
            throw ValidationException::withMessages([
                'holiday_calendar_id' => 'The selected holiday calendar must belong to the selected company.',
            ]);
        }
    }

    private function validateType(): void
    {
        if (! array_key_exists((string) $this->type, self::typeOptions())) {
            throw ValidationException::withMessages([
                'type' => 'The selected holiday type is invalid.',
            ]);
        }
    }
}
