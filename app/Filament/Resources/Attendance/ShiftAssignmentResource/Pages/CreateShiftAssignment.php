<?php

namespace App\Filament\Resources\Attendance\ShiftAssignmentResource\Pages;

use App\Filament\Resources\Attendance\ShiftAssignmentResource;
use App\Models\Employee;
use Filament\Resources\Pages\CreateRecord;

class CreateShiftAssignment extends CreateRecord
{
    protected static string $resource = ShiftAssignmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user instanceof Employee) {
            $data['assigned_by'] = $user->getKey();
        }

        return $data;
    }
}
