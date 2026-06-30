<?php

namespace App\Filament\Resources\AttendancePayrollSnapshots\Pages;

use App\Filament\Resources\AttendancePayrollSnapshots\AttendancePayrollSnapshotResource;
use App\Models\Employee;
use App\Services\AttendancePayrollReadinessService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListAttendancePayrollSnapshots extends ListRecords
{
    protected static string $resource = AttendancePayrollSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateSnapshot')
                ->label('Generate Snapshot')
                ->icon('heroicon-o-plus')
                ->schema([
                    Select::make('employee_id')
                        ->label('Employee')
                        ->options(AttendancePayrollSnapshotResource::employeeOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    DatePicker::make('period_start')
                        ->label('Period Start')
                        ->default(now(config('app.timezone'))->startOfMonth()->toDateString())
                        ->required(),
                    DatePicker::make('period_end')
                        ->label('Period End')
                        ->default(now(config('app.timezone'))->endOfMonth()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data, AttendancePayrollReadinessService $service): void {
                    $user = Auth::user();
                    $employee = Employee::query()->findOrFail($data['employee_id']);

                    $service->generateSnapshot(
                        employee: $employee,
                        periodStart: $data['period_start'],
                        periodEnd: $data['period_end'],
                        actor: $user instanceof Employee ? $user : null,
                    );

                    Notification::make()
                        ->title('Attendance payroll snapshot calculated.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
