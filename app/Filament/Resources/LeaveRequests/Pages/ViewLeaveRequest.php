<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveRequests\Schemas\LeaveRequestInfolist;
use App\Models\LeaveRequest;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewLeaveRequest extends ViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return LeaveRequestInfolist::configure($schema);
    }

    protected function resolveRecord(int|string $key): LeaveRequest
    {
        return parent::resolveRecord($key)->load([
            'company',
            'employee',
            'leaveType',
            'leaveEntitlement',
            'attachment',
            'cancelledBy',
        ]);
    }
}
