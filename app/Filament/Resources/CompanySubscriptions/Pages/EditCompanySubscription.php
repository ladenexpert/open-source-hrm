<?php

namespace App\Filament\Resources\CompanySubscriptions\Pages;

use App\Filament\Resources\CompanySubscriptions\CompanySubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompanySubscription extends EditRecord
{
    protected static string $resource = CompanySubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
