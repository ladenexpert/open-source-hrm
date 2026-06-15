<?php

namespace App\Filament\Resources\Banks;

use App\Filament\Resources\Banks\Pages\ManageBanks;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\Bank;

class BankResource extends ScopedMasterDataResource
{
    protected static ?string $model = Bank::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 16;

    public static function getPages(): array
    {
        return [
            'index' => ManageBanks::route('/'),
        ];
    }
}
