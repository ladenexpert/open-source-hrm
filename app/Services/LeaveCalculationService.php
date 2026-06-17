<?php

namespace App\Services;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeaveCalculationService
{
    public function calculateDays(
        Carbon $startDate,
        Carbon $endDate,
        Collection $workdayDays,
        Collection $holidayDates,
        bool $isHalfDay = false,
        ?string $halfDayType = null,
    ): float {
        $startDate = $startDate->copy()->startOfDay();
        $endDate = $endDate->copy()->startOfDay();

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after the start date.',
            ]);
        }

        if ($isHalfDay) {
            if (! $startDate->equalTo($endDate)) {
                throw ValidationException::withMessages([
                    'is_half_day' => 'Half-day leave requests must start and end on the same day.',
                ]);
            }

            if (! in_array($halfDayType, [
                LeaveRequest::HALF_DAY_FIRST,
                LeaveRequest::HALF_DAY_SECOND,
            ], true)) {
                throw ValidationException::withMessages([
                    'half_day_type' => 'Half-day type must be either first half or second half.',
                ]);
            }

            return 0.5;
        }

        $workingDays = $workdayDays
            ->map(fn ($day): int => (int) $day)
            ->values();

        $holidayMap = $holidayDates
            ->mapWithKeys(function ($holidayDate): array {
                $date = $holidayDate instanceof Carbon
                    ? $holidayDate->toDateString()
                    : Carbon::parse($holidayDate)->toDateString();

                return [$date => true];
            });

        $days = 0.0;
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            if ($workingDays->contains($cursor->dayOfWeek) && ! $holidayMap->has($cursor->toDateString())) {
                $days += 1.0;
            }

            $cursor->addDay();
        }

        return $days;
    }
}
