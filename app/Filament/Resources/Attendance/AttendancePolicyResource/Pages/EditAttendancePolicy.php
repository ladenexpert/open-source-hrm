<?php

namespace App\Filament\Resources\Attendance\AttendancePolicyResource\Pages;

use App\Filament\Resources\Attendance\AttendancePolicyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendancePolicy extends EditRecord
{
    protected static string $resource = AttendancePolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
