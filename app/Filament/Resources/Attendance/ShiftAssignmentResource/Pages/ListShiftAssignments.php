<?php

namespace App\Filament\Resources\Attendance\ShiftAssignmentResource\Pages;

use App\Filament\Resources\Attendance\ShiftAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShiftAssignments extends ListRecords
{
    protected static string $resource = ShiftAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
