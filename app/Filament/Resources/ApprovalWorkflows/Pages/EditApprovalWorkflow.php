<?php

namespace App\Filament\Resources\ApprovalWorkflows\Pages;

use App\Filament\Resources\ApprovalWorkflows\ApprovalWorkflowResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApprovalWorkflow extends EditRecord
{
    protected static string $resource = ApprovalWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
