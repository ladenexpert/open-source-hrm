<?php

namespace App\Filament\Employee\Resources\AttendanceCorrections\Pages;

use App\Filament\Employee\Resources\AttendanceCorrections\AttendanceCorrectionResource;
use App\Filament\Resources\Attendance\AttendanceCorrectionResource\Schemas\AttendanceCorrectionInfolist;
use App\Models\AttendanceCorrection;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewAttendanceCorrection extends ViewRecord
{
    protected static string $resource = AttendanceCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return AttendanceCorrectionInfolist::configure($schema);
    }

    protected function resolveRecord(int|string $key): AttendanceCorrection
    {
        return parent::resolveRecord($key)->load([
            'company',
            'employee',
            'attendanceSummary',
            'requestedWorkLocation',
            'approvedWorkLocation',
            'approvalRequest.workflow.steps',
            'approvalRequest.steps.approver',
            'approvalRequest.steps.workflowStep',
            'approvalRequest.logs.actor',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'cancelledBy',
        ]);
    }
}
