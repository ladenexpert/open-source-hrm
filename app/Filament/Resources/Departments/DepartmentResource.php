<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Schemas\DepartmentTable;
use Filament\Schemas\Schema;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Models\Department;
use App\Models\Employee;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\Departments\Schemas\DepartmentForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';
    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    public static function form(Schema $schema): Schema
    {
        return DepartmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => static::scopeEloquentQuery($query));
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeEloquentQuery(parent::getEloquentQuery());
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
            'index' => ListDepartments::route('/'),
            // 'create' => Pages\CreateDepartment::route('/create'),
            // 'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }

    protected static function scopeEloquentQuery(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->canManageHrMasterData()) {
            return $query;
        }

        if ($user->isDepartmentManager()) {
            return $query->whereIn('id', $user->managedDepartments()->select('id'));
        }

        return $query->whereRaw('1 = 0');
    }
}
