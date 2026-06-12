<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToCompany;

    protected $table = 'messages';

    protected $fillable = [
        'company_id',
        'topic_id',
        'sender_id',
        'content',
        'read_at',
        'receiver_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'topic_id' => 'integer',
        'sender_id' => 'integer',
        'content' => 'string',
        'read_at' => 'datetime',
        'receiver_id' => 'integer',
    ];

    protected $with = ['sender'];

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->topic_id)) {
            return Topic::query()->whereKey($this->topic_id)->value('company_id');
        }

        if (filled($this->sender_id)) {
            return Employee::query()->whereKey($this->sender_id)->value('company_id');
        }

        if (filled($this->receiver_id)) {
            return Employee::query()->whereKey($this->receiver_id)->value('company_id');
        }

        return null;
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sender_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'receiver_id');
    }
}
