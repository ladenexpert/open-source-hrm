<?php

namespace App\Filament\Resources\Payrolls;

use App\Filament\Resources\Payrolls\Pages\ListPayrolls;
use App\Filament\Resources\Payrolls\Schema\PayrollForm;
use App\Filament\Resources\Payrolls\Schema\PayrollTable;
use App\Models\Employee;
use App\Models\Payroll;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PayrollResource extends Resource
{
    // TODO: Global search
    // TODO: Add icons
    protected static ?string $model = Payroll::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'heroicon-s-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return PayrollForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollTable::configure($table)
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
            'index' => ListPayrolls::route('/'),
            // 'create' => Pages\CreatePayroll::route('/create'),
            // 'edit' => Pages\EditPayroll::route('/{record}/edit'),
        ];
    }

    protected static function scopeEloquentQuery(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $query->forCompany($user->getEffectiveCompanyId());

        if ($user->canManagePayroll()) {
            return $query;
        }

        return $query->where('employee_id', $user->id);
    }
}
