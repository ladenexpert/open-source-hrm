<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Filament\Resources\PayrollRuns\Schemas\PayrollRunInfolist;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\PayrollRunService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewPayrollRun extends ViewRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prepare')
                ->label($this->record->isDraft() ? 'Prepare Run' : 'Re-prepare Run')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn (): bool => Auth::user()?->can('prepare', $this->record) ?? false)
                ->schema([
                    Toggle::make('include_all_active_employees')
                        ->label('Include All Active Employees')
                        ->default(false),
                    Select::make('employee_ids')
                        ->label('Selected Employees')
                        ->options(fn (): array => PayrollRunResource::employeeOptions($this->record->payroll_period_id))
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data): void {
                    app(PayrollRunService::class)->prepareRun(
                        payrollRun: $this->record,
                        employeeIds: $data['employee_ids'] ?? [],
                        includeAllActiveEmployees: (bool) ($data['include_all_active_employees'] ?? false),
                        actor: Auth::user() instanceof Employee ? Auth::user() : null,
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Payroll run prepared.')
                        ->success()
                        ->send();
                }),
            Action::make('lock')
                ->icon('heroicon-o-lock-closed')
                ->color('primary')
                ->visible(fn (): bool => Auth::user()?->can('lock', $this->record) ?? false)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(PayrollRunService::class)->lockRun(
                        payrollRun: $this->record,
                        actor: Auth::user() instanceof Employee ? Auth::user() : null,
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Payroll run locked.')
                        ->success()
                        ->send();
                }),
            Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => Auth::user()?->can('approve', $this->record) ?? false)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(PayrollRunService::class)->approveRun(
                        payrollRun: $this->record,
                        actor: Auth::user() instanceof Employee ? Auth::user() : null,
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Payroll run approved.')
                        ->success()
                        ->send();
                }),
            Action::make('cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => Auth::user()?->can('cancel', $this->record) ?? false)
                ->schema([
                    Textarea::make('reason')
                        ->label('Cancellation Reason')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(PayrollRunService::class)->cancelRun(
                        payrollRun: $this->record,
                        actor: Auth::user() instanceof Employee ? Auth::user() : null,
                        reason: $data['reason'] ?? null,
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Payroll run cancelled.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return PayrollRunInfolist::configure($schema);
    }

    protected function resolveRecord(int|string $key): PayrollRun
    {
        return parent::resolveRecord($key)->load([
            'company',
            'payrollPeriod',
            'lockedBy',
            'approvedBy',
            'cancelledBy',
            'payrollRunEmployees.employee',
            'payrollRunEmployees.attendancePayrollSnapshot',
        ]);
    }
}
