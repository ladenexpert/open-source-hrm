<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\PayrollRunService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var PayrollRun $payrollRun */
        $payrollRun = app(PayrollRunService::class)->createRun(
            payrollPeriod: (int) $data['payroll_period_id'],
            runType: (string) $data['run_type'],
            runCode: $data['run_code'] ?? null,
            actor: Auth::user() instanceof Employee ? Auth::user() : null,
        );

        $employeeIds = $data['employee_ids'] ?? [];
        $includeAllActiveEmployees = (bool) ($data['include_all_active_employees'] ?? false);

        if ($employeeIds !== [] || $includeAllActiveEmployees) {
            $payrollRun = app(PayrollRunService::class)->prepareRun(
                payrollRun: $payrollRun,
                employeeIds: $employeeIds,
                includeAllActiveEmployees: $includeAllActiveEmployees,
                actor: Auth::user() instanceof Employee ? Auth::user() : null,
            );
        }

        return $payrollRun;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
