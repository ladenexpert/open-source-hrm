<?php

namespace App\Filament\Employee\Resources\AttendanceCorrections\Pages;

use App\Filament\Employee\Resources\AttendanceCorrections\AttendanceCorrectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceCorrections extends ListRecords
{
    protected static string $resource = AttendanceCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Attendance Correction'),
        ];
    }
}
