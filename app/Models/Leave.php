<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use BelongsToCompany;

    protected $table = 'leave';

    protected $fillable = [
        'company_id',
        'employee_id',
        'actioned_by',
        'leave_type',
        'start_date',
        'end_date',
        'status',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'leave_date' => 'datetime:H:i',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $appends = [
        'duration',

    ];

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        return null;
    }

    public function getDurationAttribute()
    {
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        return $start->diffInDays($end);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'actioned_by');
    }
}
