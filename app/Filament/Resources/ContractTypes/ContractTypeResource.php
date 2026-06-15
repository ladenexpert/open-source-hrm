<?php

namespace App\Filament\Resources\ContractTypes;

use App\Filament\Resources\ContractTypes\Pages\ManageContractTypes;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\ContractType;

class ContractTypeResource extends ScopedMasterDataResource
{
    protected static ?string $model = ContractType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 14;

    public static function getPages(): array
    {
        return [
            'index' => ManageContractTypes::route('/'),
        ];
    }
}
