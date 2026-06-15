<?php

namespace App\Filament\Resources\MaritalStatuses;

use App\Filament\Resources\MaritalStatuses\Pages\ManageMaritalStatuses;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\MaritalStatus;

class MaritalStatusResource extends ScopedMasterDataResource
{
    protected static ?string $model = MaritalStatus::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 18;

    public static function getPages(): array
    {
        return [
            'index' => ManageMaritalStatuses::route('/'),
        ];
    }
}
