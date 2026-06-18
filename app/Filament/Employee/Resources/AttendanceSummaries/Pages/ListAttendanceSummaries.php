<?php

namespace App\Filament\Employee\Resources\AttendanceSummaries\Pages;

use App\Filament\Employee\Resources\AttendanceSummaries\AttendanceSummaryResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSummaries extends ListRecords
{
    protected static string $resource = AttendanceSummaryResource::class;
}
