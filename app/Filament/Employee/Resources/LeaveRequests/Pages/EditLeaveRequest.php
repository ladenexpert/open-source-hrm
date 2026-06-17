<?php

namespace App\Filament\Employee\Resources\LeaveRequests\Pages;

use App\Filament\Employee\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\Employee;
use App\Services\LeaveRequestService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Employee $employee */
        $employee = Auth::user();
        /** @var LeaveRequestService $service */
        $service = app(LeaveRequestService::class);

        $leaveRequest = $service->updateDraft($record, $data);
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

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Leave request draft updated successfully.';
    }
}
