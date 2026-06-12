<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\shift;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->options(function () {
                        return Employee::all()->pluck('name', 'id');
                    })
                    ->label('Employee')
                    ->required()
                    ->searchable([
                        'first_name',
                        'last_name',
                    ]),
                Select::make('shift_id')
                    ->options(function () {
                        return shift::all()->pluck('name', 'id');
                    })
                    ->preload()
                    ->label('Shift')
                    ->searchable()
                    ->createOptionForm(
                        [
                            TextInput::make('name')
                                ->required()
                                ->label('Shift Name'),
                            Grid::make(2)->schema([

                                TimePicker::make('start_time')
                                    ->required()
                                    ->label('Start Time')
                                    ->time(),
                                TimePicker::make('end_time')
                                    ->required()
                                    ->label('End Time')
                                    ->time(),
                            ]),

                        ]
                    )
                    ->createOptionUsing(function (array $data) {
                        return shift::create([
                            'name' => $data['name'],
                            'start_time' => $data['start_time'],
                            'end_time' => $data['end_time'],

                        ])->id;
                    }),
                DatePicker::make('date')
                    ->unique(
                        table: Attendance::class,
                        column: 'date',
                        ignoreRecord: true
                    )
                    ->required()
                    ->label('Attendance Date'),
                TimePicker::make('clock_in')
                    ->required()
                    ->label('Clock In Time')
                    ->time(),
                TimePicker::make('clock_out')
                    // ->required()
                    ->label('Clock Out Time')
                    ->time(),

                Textarea::make('remarks')
                    ->label('Remarks')
                    ->maxLength(255)
                    ->nullable()
                    ->autosize()
                    ->columnSpanFull(),
            ]);
    }
}
