<?php

namespace App\Filament\Employee\Resources\AttendanceCorrections\Pages;

use App\Filament\Employee\Resources\AttendanceCorrections\AttendanceCorrectionResource;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCorrectionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateAttendanceCorrection extends CreateRecord
{
    protected static string $resource = AttendanceCorrectionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Employee $employee */
        $employee = Auth::user();

        return app(AttendanceCorrectionService::class)->createDraft($employee, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Attendance correction draft saved successfully.';
    }
}
