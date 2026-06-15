<?php

namespace App\Filament\Resources\EmploymentStatuses;

use App\Filament\Resources\EmploymentStatuses\Pages\ManageEmploymentStatuses;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\EmploymentStatus;

class EmploymentStatusResource extends ScopedMasterDataResource
{
    protected static ?string $model = EmploymentStatus::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?int $navigationSort = 12;

    public static function getPages(): array
    {
        return [
            'index' => ManageEmploymentStatuses::route('/'),
        ];
    }
}
