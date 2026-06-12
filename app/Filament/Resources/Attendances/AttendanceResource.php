<?php

namespace App\Filament\Resources\Attendances;

use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Resources\Attendances\Schemas\AttendanceForm;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if (! $user instanceof Employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $query->forCompany($user->getEffectiveCompanyId());

        if ($user->canManageHrMasterData()) {
            return $query;
        }

        if ($user->isDepartmentManager()) {
            return $query->whereHas('employee', fn (Builder $employeeQuery): Builder => $employeeQuery
                ->whereIn('department_id', $user->managedDepartments()->select('id')));
        }

        return $query->where('employee_id', $user->id);
    }

    public static function form(Schema $schema): Schema
    {
        return AttendanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                static::getEloquentQuery()
                    ->with(['employee', 'shift'])
                    ->withoutGlobalScopes([SoftDeletingScope::class])
                    ->latest()
            )
            ->columns([
                TextColumn::make('employee.employee_code')
                    ->label('Employee code')
                    ->searchable([
                        'first_name',
                        'last_name',

                    ])
                    ->sortable(
                        [
                            'first_name',
                            'last_name',
                        ]
                    ),
                TextColumn::make('employee.name')
                    ->searchable([
                        'first_name',
                        'last_name',

                    ])
                    ->sortable(
                        [
                            'first_name',
                            'last_name',
                        ]
                    )
                    ->label('Name'),
                TextColumn::make('shift.name')
                    ->label('Shift')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()

                    ->label(' Date')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('clock_in')
                    ->dateTime('H:i')
                    ->searchable()
                    ->sortable()
                    ->label('Clock In Time'),
                TextColumn::make('clock_out')
                    ->dateTime('H:i')
                    ->searchable()
                    ->sortable()
                    ->label('Clock Out Time'),
                TextColumn::make('hours')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()

                    ->label('Hours'),
                TextColumn::make('remarks')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Remarks'),
            ])
            ->filters(
                [
                    SelectFilter::make('employee_id')
                        ->label('Employee')
                        ->searchable(
                            [
                                'first_name',
                                'last_name',
                            ]
                        )
                        ->options(
                            fn (): array => static::getEloquentQuery()
                                ->with('employee')
                                ->get()
                                ->pluck('employee.name', 'employee.id')
                                ->filter()
                                ->all()
                        ),
                    SelectFilter::make('shift_id')
                        ->label('Shift')
                        ->options(
                            fn (): array => Shift::query()
                                ->when(
                                    Auth::user() instanceof Employee && ! Auth::user()->isSuperAdmin(),
                                    fn (Builder $shiftQuery): Builder => $shiftQuery->forCompany(Auth::user()->getEffectiveCompanyId()),
                                )
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                        ),
                    Filter::make('date')
                        ->schema([
                            DatePicker::make('date')
                                ->label('Select Date')
                                ->required(),
                            // ->default(now())
                        ])
                        ->query(function (Builder $query, array $data) {
                            if (isset($data['date'])) {
                                return $query->whereDate('date', $data['date']);
                            }

                            return $query;
                        }),

                ]

            )
            ->recordActions([
                ActionGroup::make([

                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListAttendances::route('/'),
            // 'create' => Pages\CreateAttendance::route('/create'),
            // 'view' => Pages\ViewAttendance::route('/{record}'),
            // 'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
