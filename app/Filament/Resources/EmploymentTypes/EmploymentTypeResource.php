<?php

namespace App\Filament\Resources\EmploymentTypes;

use App\Filament\Resources\EmploymentTypes\Pages\ManageEmploymentTypes;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\EmploymentType;

class EmploymentTypeResource extends ScopedMasterDataResource
{
    protected static ?string $model = EmploymentType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 13;

    public static function getPages(): array
    {
        return [
            'index' => ManageEmploymentTypes::route('/'),
        ];
    }
}
