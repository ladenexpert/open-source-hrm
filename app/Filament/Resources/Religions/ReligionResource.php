<?php

namespace App\Filament\Resources\Religions;

use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Filament\Resources\Religions\Pages\ManageReligions;
use App\Models\Religion;

class ReligionResource extends ScopedMasterDataResource
{
    protected static ?string $model = Religion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 17;

    public static function getPages(): array
    {
        return [
            'index' => ManageReligions::route('/'),
        ];
    }
}
