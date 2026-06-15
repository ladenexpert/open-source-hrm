<?php

namespace App\Filament\Resources\IdentityTypes;

use App\Filament\Resources\IdentityTypes\Pages\ManageIdentityTypes;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\IdentityType;

class IdentityTypeResource extends ScopedMasterDataResource
{
    protected static ?string $model = IdentityType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 15;

    public static function getPages(): array
    {
        return [
            'index' => ManageIdentityTypes::route('/'),
        ];
    }
}
