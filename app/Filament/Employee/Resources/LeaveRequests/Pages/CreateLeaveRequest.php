<?php

namespace App\Filament\Employee\Resources\LeaveRequests\Pages;

use App\Filament\Employee\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Employee $employee */
        $employee = Auth::user();
        /** @var LeaveRequestService $service */
        $service = app(LeaveRequestService::class);

        $leaveRequest = $service->createDraft($employee, $data);
        $attachment = LeaveRequestResource::resolveUploadedFile($data['attachment'] ?? null);

        if ($attachment) {
            $service->storeAttachment($leaveRequest, $attachment, $employee);
        }

        return $leaveRequest;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Leave request draft saved successfully.';
    }
}
