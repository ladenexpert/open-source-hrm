<?php

namespace App\Filament\Resources\Attendance\AttendanceLogResource\Pages;

use App\Filament\Resources\Attendance\AttendanceLogResource;
use App\Filament\Resources\Attendance\AttendanceLogResource\Schemas\AttendanceLogInfolist;
use App\Models\AttendanceLog;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewAttendanceLog extends ViewRecord
{
    protected static string $resource = AttendanceLogResource::class;

    public function infolist(Schema $schema): Schema
    {
        return AttendanceLogInfolist::configure($schema);
    }

    protected function resolveRecord(int|string $key): AttendanceLog
    {
        return parent::resolveRecord($key)->load([
            'company',
            'employee',
            'workLocation',
            'shiftPattern',
            'createdBy',
            'attendanceSelfie',
        ]);
    }
}
