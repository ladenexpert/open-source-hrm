<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use BelongsToCompany;

    protected $table = 'shifts';

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',

    ];

    protected $casts = [
        'company_id' => 'integer',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',

    ];

    protected $appends = [
        'duration',
    ];

    public function getDurationAttribute()
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        return $start->diffInMinutes($end);
    }
}
