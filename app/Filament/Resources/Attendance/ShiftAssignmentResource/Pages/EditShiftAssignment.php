<?php

namespace App\Filament\Resources\Attendance\ShiftAssignmentResource\Pages;

use App\Filament\Resources\Attendance\ShiftAssignmentResource;
use App\Models\Employee;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShiftAssignment extends EditRecord
{
    protected static string $resource = ShiftAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if ($user instanceof Employee) {
            $data['assigned_by'] = $user->getKey();
        }

        return $data;
    }
}
