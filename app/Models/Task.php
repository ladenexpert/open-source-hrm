<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'status',
        'sort_order',
        'assignee_id',

        'due_date',
        'position',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'title' => 'string',
        'description' => 'string',
        'status' => 'string',
        'sort_order' => 'integer',
        'assignee_id' => 'integer',
        'due_date' => 'datetime',
        'position' => 'integer',
    ];

    protected $table = 'tasks';

    protected $appends = ['date', 'email'];

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->assignee_id)) {
            return Employee::query()->whereKey($this->assignee_id)->value('company_id');
        }

        return null;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }

    public function getDateAttribute()
    {
        return $this->due_date?->format('d-M-Y');
    }

    public function getEmailAttribute()
    {
        return $this->assignee?->email;
    }
}
