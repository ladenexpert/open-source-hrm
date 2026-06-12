<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Model;

class Event extends Model implements Eventable
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'all_day',
        'color',
        'type',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'all_day' => 'boolean',
    ];

    public function toCalendarEvent(): CalendarEvent
    {
        // For eloquent models, make sure to pass the model to the constructor
        return CalendarEvent::make($this)
            ->title($this->title)
            ->start($this->start_time)
            ->end($this->end_time)
            ->allDay($this->all_day)
            ->backgroundColor($this->getEventColor())
            ->textColor('#ffffff')
            // ->id($this->id)
            ->action('edit');

    }

    protected function getEventColor(): string
    {
        return match ($this->type) {
            'meeting' => '#3b82f6',
            'appointment' => '#10b981',
            'deadline' => '#ef4444',
            'event' => '#8b5cf6',
            default => 'primary',
        };
    }
}
