<?php

namespace App\Filament\Resources\Admins;

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Filament\Resources\Admins\Pages\ViewAdmin;
use App\Filament\Resources\Employees\Schemas\EmployeeForm as AdminForm;
use App\Filament\Resources\Employees\Schemas\EmployeeTable as AdminTable;
use App\Models\Employee;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AdminResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $label = 'Admin';

    protected static ?string $pluralLabel = 'Admins';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AdminForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminTable::configure($table)
            ->modifyQueryUsing(
                function (Builder $query) {
                    $query->role('admin');
                }
            );
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('admin');
    }

    public static function canAccess(): bool
    {
        return static::userCanManageAdmins();
    }

    public static function canViewAny(): bool
    {
        return static::userCanManageAdmins();
    }

    public static function canCreate(): bool
    {
        return static::userCanManageAdmins();
    }

    public static function canView(Model $record): bool
    {
        return static::userCanManageAdmins()
            && $record instanceof Employee
            && $record->hasNormalizedRole('admin');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanManageAdmins();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'view' => ViewAdmin::route('/{record}'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }

    protected static function userCanManageAdmins(): bool
    {
        $user = Auth::user();

        return $user instanceof Employee && $user->canManageHrMasterData();
    }
}
