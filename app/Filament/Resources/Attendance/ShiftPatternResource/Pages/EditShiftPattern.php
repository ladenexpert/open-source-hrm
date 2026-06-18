<?php

namespace App\Filament\Resources\Attendance\ShiftPatternResource\Pages;

use App\Filament\Resources\Attendance\ShiftPatternResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShiftPattern extends EditRecord
{
    protected static string $resource = ShiftPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
