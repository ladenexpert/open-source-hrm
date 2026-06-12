<?php

namespace App\Filament\Widgets;

use App\Models\Employee;
use App\Models\Event;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Guava\Calendar\Filament\Actions\CreateAction;
use Guava\Calendar\Filament\Actions\DeleteAction;
use Guava\Calendar\Filament\Actions\EditAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\EventDropInfo;
use Guava\Calendar\ValueObjects\EventResizeInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AllCalendarWidget extends CalendarWidget
{
    protected static ?string $title = 'Calendar';

    protected static ?int $sort = 2;

    protected bool $eventDragEnabled = true;

    protected bool $dateClickEnabled = true;

    protected bool $eventClickEnabled = true;

    protected bool $eventResizeEnabled = true;

    public function createEventAction(): CreateAction
    {
        return $this->createAction(Event::class)
            ->label('New')
            ->icon('heroicon-o-plus')
            ->schema([
                TextInput::make('title')
                    ->required(),
                Textarea::make('description'),
                Grid::make(2)
                    ->schema([
                        Select::make('type')
                            ->options([
                                'meeting' => 'Meeting',
                                'appointment' => 'Appointment',
                                'deadline' => 'Deadline',
                                'event' => 'Event',
                            ]),
                        Toggle::make('all_day')
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {

                                if ($state) {
                                    $set('start_time', now()->startOfDay());
                                    $set('end_time', now()->endOfDay());

                                }
                            })

                            ->label('All day'),

                    ]),
                Grid::make(2)
                    ->schema([
                        DateTimePicker::make('start_time')
                            ->required()
                            ->readOnly(fn ($get) => $get('all_day')),
                        DateTimePicker::make('end_time')
                            ->required()
                            ->readOnly(fn ($get) => $get('all_day')),

                    ]),

            ]);
    }

    public function editEventAction(): EditAction
    {
        return $this->editAction(Event::class)
            ->label('Edit')
            ->icon('heroicon-o-pencil')
            ->schema([
                TextInput::make('title')
                    ->required(),
                Textarea::make('description'),
                Grid::make(2)
                    ->schema([
                        Select::make('type')
                            ->options([
                                'meeting' => 'Meeting',
                                'appointment' => 'Appointment',
                                'deadline' => 'Deadline',
                                'event' => 'Event',
                            ]),
                        Toggle::make('all_day')
                            ->inlineLabel(false)
                            ->label('All day'),

                    ]),
                Grid::make(2)
                    ->schema([
                        DateTimePicker::make('start_time')
                            ->required(),
                        DateTimePicker::make('end_time')
                            ->required(),
                    ]),

            ]);
    }

    public function viewEventAction(): ViewAction
    {
        return $this->viewAction()
            ->icon('heroicon-o-eye')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('title')
                            ->hiddenLabel()
                            ->hint('Title'),
                        TextEntry::make('type')
                            ->hiddenLabel()
                            ->badge()
                            ->color(fn ($record) => match ($record->type) {
                                'meeting' => 'info',
                                'appointment' => 'success',
                                'deadline' => 'danger',
                                'event' => 'warning',
                                default => 'gray',
                            })
                            ->hint('Type'),

                    ]),
                TextEntry::make('description')
                    ->hiddenLabel()
                    ->hint('Description'),
                Grid::make(2)
                    ->schema([
                        TextEntry::make('start_time')
                            ->dateTime(),
                        TextEntry::make('end_time')
                            ->dateTime(),
                    ]),
            ]);
    }

    public function deleteEventAction(): DeleteAction
    {
        return DeleteAction::make('deleteEvent')
            ->model(Event::class);

    }

    public function onEventDrop(EventDropInfo $info, Model $event): bool
    {

        $event->update([
            'start_time' => $info->event->getStart(),
            'end_time' => $info->event->getEnd(),
        ]);

        return true;

    }

    public function onEventResize(EventResizeInfo $info, Model $event): bool
    {
        // dd($info);
        $event->update([
            'start_time' => $info->event->getStart(),
            'end_time' => $info->event->getEnd(),
        ]);

        return true;
    }

    public function getHeaderActions(): array
    {
        return [
            $this->createEventAction(),

        ];
    }

    protected function getDateClickContextMenuActions(): array
    {
        return [
            $this->createEventAction(),

        ];
    }

    public function getEventClickContextMenuActions(): array
    {
        return [
            $this->viewEventAction(),
            $this->editEventAction(),
            $this->deleteEventAction(),
        ];
    }

    // protected string $view = 'filament.widgets.calendar-widget';
    public function getEvents(FetchInfo $info): array|Collection|Builder
    {
        $start = $info->start;
        $end = $info->end;

        return Event::query()
            ->when(
                Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                fn (Builder $query): Builder => $query->forCompany(Auth::user()->getEffectiveCompanyId()),
            )
            ->where(function ($query) use ($start, $end) {
                $query
                    ->orWhereBetween('end_time', [$start, $end])
                    ->whereBetween('start_time', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->where('start_time', '<', $start)
                            ->where('end_time', '>', $end);
                    });

            });
    }
}
