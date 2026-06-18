<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ShiftPatternDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'shift_pattern_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_duration_minutes',
        'is_working_day',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'shift_pattern_id' => 'integer',
        'day_of_week' => 'integer',
        'break_duration_minutes' => 'integer',
        'is_working_day' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $detail): void {
            $detail->company_id ??= ShiftPattern::query()
                ->whereKey($detail->shift_pattern_id)
                ->value('company_id');

            if ($detail->day_of_week < 1 || $detail->day_of_week > 7) {
                throw ValidationException::withMessages([
                    'day_of_week' => 'Day of week must follow the existing 1 (Monday) through 7 (Sunday) convention.',
                ]);
            }

            if ($detail->break_duration_minutes < 0) {
                throw ValidationException::withMessages([
                    'break_duration_minutes' => 'Break duration minutes must be zero or greater.',
                ]);
            }

            if (! $detail->is_working_day) {
                $detail->start_time = null;
                $detail->end_time = null;

                return;
            }

            if (blank($detail->start_time) || blank($detail->end_time)) {
                throw ValidationException::withMessages([
                    'start_time' => 'Working day details must define both start and end times.',
                ]);
            }
        });

        static::saved(function (self $detail): void {
            $detail->shiftPattern()->first()?->syncOvernightFlag();
        });

        static::deleted(function (self $detail): void {
            ShiftPattern::withTrashed()->find($detail->shift_pattern_id)?->syncOvernightFlag();
        });
    }

    /**
     * @return array<int, string>
     */
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    public function isOvernight(): bool
    {
        if (! $this->is_working_day || blank($this->start_time) || blank($this->end_time)) {
            return false;
        }

        return $this->end_time <= $this->start_time;
    }

    public function workDurationMinutes(): int
    {
        if (! $this->is_working_day || blank($this->start_time) || blank($this->end_time)) {
            return 0;
        }

        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        if ($this->isOvernight()) {
            $end->addDay();
        }

        return max(0, $start->diffInMinutes($end) - (int) $this->break_duration_minutes);
    }
}
