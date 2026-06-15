<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkdayPatternDay extends Model
{
    protected $fillable = [
        'workday_pattern_id',
        'day_of_week',
        'is_working_day',
        'working_hours',
    ];

    protected $casts = [
        'workday_pattern_id' => 'integer',
        'day_of_week' => 'integer',
        'is_working_day' => 'boolean',
        'working_hours' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $workdayPatternDay): void {
            if ($workdayPatternDay->day_of_week < 1 || $workdayPatternDay->day_of_week > 7) {
                throw ValidationException::withMessages([
                    'day_of_week' => 'Day of week must be between 1 (Monday) and 7 (Sunday).',
                ]);
            }

            if (filled($workdayPatternDay->working_hours) && (float) $workdayPatternDay->working_hours < 0) {
                throw ValidationException::withMessages([
                    'working_hours' => 'Working hours must be zero or greater.',
                ]);
            }
        });
    }

    public static function dayOptions(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }

    public function workdayPattern(): BelongsTo
    {
        return $this->belongsTo(WorkdayPattern::class);
    }
}
