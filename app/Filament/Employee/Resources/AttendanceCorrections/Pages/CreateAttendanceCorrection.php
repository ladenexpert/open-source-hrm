<?php

namespace App\Filament\Employee\Resources\AttendanceCorrections\Pages;

use App\Filament\Employee\Resources\AttendanceCorrections\AttendanceCorrectionResource;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCorrectionService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateAttendanceCorrection extends CreateRecord
{
    protected static string $resource = AttendanceCorrectionResource::class;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->getPrefillData());

        $this->callHook('afterFill');
    }

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

    /**
     * @return array<string, mixed>
     */
    protected function getPrefillData(): array
    {
        $employee = Auth::user();

        if (! $employee instanceof Employee) {
            return [];
        }

        $summaryId = request()->integer('attendance_summary');

        if (filled($summaryId)) {
            $summary = AttendanceSummary::query()
                ->forCompany($employee->getEffectiveCompanyId())
                ->forEmployee($employee)
                ->whereKey($summaryId)
                ->first();

            if ($summary instanceof AttendanceSummary) {
                return [
                    'attendance_date' => $summary->attendance_date?->toDateString(),
                    'requested_work_location_id' => $summary->work_location_id,
                ];
            }
        }

        $attendanceDate = trim((string) request()->query('attendance_date', ''));

        if ($attendanceDate === '') {
            return [];
        }

        try {
            return [
                'attendance_date' => Carbon::parse($attendanceDate, config('app.timezone'))->toDateString(),
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
