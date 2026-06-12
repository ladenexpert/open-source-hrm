<?php

namespace App\Filament\Resources\WorkLocations\Pages;

use App\Filament\Resources\WorkLocations\WorkLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkLocation extends EditRecord
{
    protected static string $resource = WorkLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
