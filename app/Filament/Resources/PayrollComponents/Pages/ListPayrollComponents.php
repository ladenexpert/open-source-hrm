<?php

namespace App\Filament\Resources\PayrollComponents\Pages;

use App\Filament\Resources\PayrollComponents\PayrollComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollComponents extends ListRecords
{
    protected static string $resource = PayrollComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
