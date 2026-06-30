<?php

namespace App\Filament\Resources\OvertimeRequests\Pages;

use App\Filament\Resources\OvertimeRequests\OvertimeRequestResource;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\OvertimeRequestService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOvertimeRequest extends CreateRecord
{
    protected static string $resource = OvertimeRequestResource::class;

    protected function handleRecordCreation(array $data): OvertimeRequest
    {
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);

        return app(OvertimeRequestService::class)->createDraft(
            $employee,
            $data,
            Auth::user(),
        );
    }
}
