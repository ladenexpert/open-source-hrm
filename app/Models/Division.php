<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Division extends MasterData
{
    protected $fillable = [
        'company_id',
        'company_group_id',
        'department_id',
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'company_group_id' => 'integer',
        'department_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (self $division): void {
            if (blank($division->department_id)) {
                return;
            }

            $department = Department::query()->find($division->department_id);

            if (! $department) {
                throw ValidationException::withMessages([
                    'department_id' => 'The selected department is invalid.',
                ]);
            }

            $division->company_id ??= $department->company_id;
            $division->company_group_id ??= $department->company_group_id;

            if (filled($division->company_id) && (int) $division->company_id !== (int) $department->company_id) {
                throw ValidationException::withMessages([
                    'department_id' => 'The selected department does not belong to the selected company.',
                ]);
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
