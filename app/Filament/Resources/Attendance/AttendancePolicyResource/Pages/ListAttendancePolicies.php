<?php

namespace App\Filament\Resources\Attendance\AttendancePolicyResource\Pages;

use App\Filament\Resources\Attendance\AttendancePolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendancePolicies extends ListRecords
{
    protected static string $resource = AttendancePolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
