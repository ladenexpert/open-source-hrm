<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'attendances';

    protected $with = ['shift', 'employee'];

    protected $fillable = [
        'company_id',
        'employee_id',
        'date',
        'clock_in',
        'clock_out',

        'shift_id',
        'remarks',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'date' => 'date',
        'clock_in' => 'datetime:H:i',
        'clock_out' => 'datetime:H:i',
        'shift_id' => 'integer',

    ];

    protected $appends = [
        'shift_name',
        'hours',
    ];

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        return null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function getShiftNameAttribute()
    {
        return $this->shift ? $this->shift->name : null;
    }

    public function getHoursAttribute()
    {
        if ($this->clock_in && $this->clock_out) {
            $start = Carbon::parse($this->clock_in);
            $end = Carbon::parse($this->clock_out);

            return $start->diffInHours($end, false) + ($start->diffInMinutes($end, false) % 60) / 60;
        }

        return null;
    }
}
