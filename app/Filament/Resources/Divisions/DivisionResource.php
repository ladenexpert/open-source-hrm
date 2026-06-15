<?php

namespace App\Filament\Resources\Divisions;

use App\Filament\Resources\Divisions\Pages\ManageDivisions;
use App\Filament\Resources\MasterData\ScopedMasterDataResource;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class DivisionResource extends ScopedMasterDataResource
{
    protected static ?string $model = Division::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 4;

    protected static function additionalFormComponents(): array
    {
        return [
            Select::make('department_id')
                ->label('Department')
                ->options(function (): array {
                    $user = Auth::user();
                    $query = Department::query()->orderBy('name');

                    if ($user instanceof Employee && ! $user->isSuperAdmin()) {
                        $query->forCompanies($user->accessibleCompanyIds());
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->preload(),
        ];
    }

    protected static function additionalTableColumns(): array
    {
        return [
            TextColumn::make('department.name')->label('Department')->toggleable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDivisions::route('/'),
        ];
    }
}
