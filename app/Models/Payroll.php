<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use BelongsToCompany;

    protected $table = 'payrolls';

    protected $fillable = [
        'company_id',
        'employee_id',
        'pay_date',
        'period',
        'gross_pay',
        'net_pay',
        'deductions',
        'allowances',
        'bonuses',
        'notes',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'pay_date' => 'date',
        'deductions' => 'array',
        'allowances' => 'array',
        'bonuses' => 'array',
    ];

    protected $with = [
        'employee',
    ];

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->employee_id)) {
            return Employee::query()->whereKey($this->employee_id)->value('company_id');
        }

        return null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
