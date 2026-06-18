<?php

namespace App\Filament\Resources\Attendance\ShiftPatternResource\Pages;

use App\Filament\Resources\Attendance\ShiftPatternResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShiftPatterns extends ListRecords
{
    protected static string $resource = ShiftPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
