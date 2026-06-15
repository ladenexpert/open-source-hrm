<?php

namespace App\Filament\Resources\JobLevels;

use App\Filament\Resources\JobLevels\Pages\ManageJobLevels;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\JobLevel;

class JobLevelResource extends ScopedMasterDataResource
{
    protected static ?string $model = JobLevel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?int $navigationSort = 10;

    public static function getPages(): array
    {
        return [
            'index' => ManageJobLevels::route('/'),
        ];
    }
}
