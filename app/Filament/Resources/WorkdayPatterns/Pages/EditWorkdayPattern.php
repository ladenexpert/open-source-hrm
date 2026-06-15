<?php

namespace App\Filament\Resources\WorkdayPatterns\Pages;

use App\Filament\Resources\WorkdayPatterns\WorkdayPatternResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkdayPattern extends EditRecord
{
    protected static string $resource = WorkdayPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
