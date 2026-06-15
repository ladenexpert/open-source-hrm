<?php

namespace App\Filament\Resources\WorkdayPatterns\Pages;

use App\Filament\Resources\WorkdayPatterns\WorkdayPatternResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkdayPatterns extends ListRecords
{
    protected static string $resource = WorkdayPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
