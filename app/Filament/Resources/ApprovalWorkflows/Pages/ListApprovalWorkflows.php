<?php

namespace App\Filament\Resources\ApprovalWorkflows\Pages;

use App\Filament\Resources\ApprovalWorkflows\ApprovalWorkflowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovalWorkflows extends ListRecords
{
    protected static string $resource = ApprovalWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
