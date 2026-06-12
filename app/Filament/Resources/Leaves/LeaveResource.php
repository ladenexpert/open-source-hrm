<?php

namespace App\Filament\Resources\Leaves;

use App\Filament\Resources\Leaves\Schemas\LeaveForm;
use App\Filament\Resources\Leaves\Schemas\LeaveTable;
use Filament\Schemas\Schema;
use App\Filament\Resources\Leaves\Pages\ListLeaves;
use App\Models\Leave;
use App\Models\Employee;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeaveResource extends Resource
{
    protected static ?string $model = Leave::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-minus';
    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';
    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Leave Requests';

    public static function form(Schema $schema): Schema
    {
        return LeaveForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaveTable::configure($table)
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
            'index' => ListLeaves::route('/'),
            // 'create' => Pages\CreateLeave::route('/create'),
            // 'view' => Pages\ViewLeave::route('/{record}'),
            // 'edit' => Pages\EditLeave::route('/{record}/edit'),
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
            return $query->whereHas('employee', fn (Builder $employeeQuery): Builder => $employeeQuery
                ->whereIn('department_id', $user->managedDepartments()->select('id')));
        }

        return $query->where('employee_id', $user->id);
    }
}
