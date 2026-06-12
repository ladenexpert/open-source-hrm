<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Schemas\EmployeeTable;
use Filament\Schemas\Schema;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Models\Employee;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeTable::configure($table)
            ->modifyQueryUsing(
                function (Builder $query): Builder {
                    return static::scopeStandardEmployees($query);
                }
            );
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeAuthorizedEmployees(parent::getEloquentQuery());
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
            'index' => ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => ViewEmployee::route('/{record}'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }

    protected static function scopeAuthorizedEmployees(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->canManageHrMasterData()) {
            return $query;
        }

        if ($user->isDepartmentManager()) {
            return $query->whereIn('department_id', $user->managedDepartments()->select('id'));
        }

        return $query->whereKey($user->id);
    }

    protected static function scopeStandardEmployees(Builder $query): Builder
    {
        return static::scopeAuthorizedEmployees($query)
            ->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery->where('name', 'employee'))
            ->whereDoesntHave('roles', fn (Builder $roleQuery): Builder => $roleQuery->whereIn('name', [
                'super_admin',
                'admin',
                'hr',
                'finance',
                'department_manager',
            ]));
    }
}
