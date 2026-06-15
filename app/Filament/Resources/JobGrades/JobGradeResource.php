<?php

namespace App\Filament\Resources\JobGrades;

use App\Filament\Resources\JobGrades\Pages\ManageJobGrades;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\JobGrade;

class JobGradeResource extends ScopedMasterDataResource
{
    protected static ?string $model = JobGrade::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-arrow-up';

    protected static ?int $navigationSort = 11;

    public static function getPages(): array
    {
        return [
            'index' => ManageJobGrades::route('/'),
        ];
    }
}
