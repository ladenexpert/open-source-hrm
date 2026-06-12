<?php

namespace App\Filament\Resources\CompanySubscriptions\Pages;

use App\Filament\Resources\CompanySubscriptions\CompanySubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanySubscriptions extends ListRecords
{
    protected static string $resource = CompanySubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
