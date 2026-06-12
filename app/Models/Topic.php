<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use BelongsToCompany;

    protected $table = 'topics';

    protected $fillable = [
        'company_id',
        'subject',
        'creator_id',
        'receiver_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'subject' => 'string',
        'creator_id' => 'integer',
        'receiver_id' => 'integer',
    ];

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->creator_id)) {
            return Employee::query()->whereKey($this->creator_id)->value('company_id');
        }

        if (filled($this->receiver_id)) {
            return Employee::query()->whereKey($this->receiver_id)->value('company_id');
        }

        return null;
    }

    public function message(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'creator_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'receiver_id');
    }
}
