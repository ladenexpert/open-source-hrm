<?php

namespace App\Events\Leave;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LeaveRequest $leaveRequest,
        public readonly ?Employee $actor = null,
    ) {
    }
}
