<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequestAttachment>
 */
class LeaveRequestAttachmentFactory extends Factory
{
    protected $model = LeaveRequestAttachment::class;

    public function definition(): array
    {
        $leaveRequest = LeaveRequest::query()->first() ?? LeaveRequest::factory()->create();

        return [
            'company_id' => $leaveRequest->company_id,
            'leave_request_id' => $leaveRequest->id,
            'path' => 'leave-requests/'.$leaveRequest->company_id.'/sample-document.pdf',
            'original_filename' => 'supporting-document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'uploaded_by' => $leaveRequest->employee_id,
        ];
    }
}
