<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\PayrollComponentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollComponent extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_BENEFIT = 'benefit';

    public const TYPE_TAX = 'tax';

    public const TYPE_EMPLOYER_CONTRIBUTION = 'employer_contribution';

    public const TYPE_INFORMATIONAL = 'informational';

    public const VALUE_TYPE_FIXED = 'fixed';

    public const VALUE_TYPE_PERCENTAGE = 'percentage';

    public const VALUE_TYPE_FORMULA_PLACEHOLDER = 'formula_placeholder';

    public const VALUE_TYPE_MANUAL = 'manual';

    protected $fillable = [
        'company_id',
        'component_code',
        'name',
        'description',
        'component_type',
        'value_type',
        'default_amount',
        'default_percentage',
        'taxable',
        'tax_deductible',
        'bpjs_applicable',
        'thr_applicable',
        'proratable',
        'recurring',
        'active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'default_amount' => 'decimal:2',
        'default_percentage' => 'decimal:4',
        'taxable' => 'boolean',
        'tax_deductible' => 'boolean',
        'bpjs_applicable' => 'boolean',
        'thr_applicable' => 'boolean',
        'proratable' => 'boolean',
        'recurring' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $component): void {
            app(PayrollComponentService::class)->validateAndNormalize($component);
        });
    }

    /**
     * @return array<int, string>
     */
    public static function componentTypes(): array
    {
        return [
            self::TYPE_EARNING,
            self::TYPE_DEDUCTION,
            self::TYPE_BENEFIT,
            self::TYPE_TAX,
            self::TYPE_EMPLOYER_CONTRIBUTION,
            self::TYPE_INFORMATIONAL,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function componentTypeLabels(): array
    {
        return [
            self::TYPE_EARNING => 'Earning',
            self::TYPE_DEDUCTION => 'Deduction',
            self::TYPE_BENEFIT => 'Benefit',
            self::TYPE_TAX => 'Tax',
            self::TYPE_EMPLOYER_CONTRIBUTION => 'Employer Contribution',
            self::TYPE_INFORMATIONAL => 'Informational',
        ];
    }

    public static function componentTypeColor(?string $type): string
    {
        return match ($type) {
            self::TYPE_EARNING => 'success',
            self::TYPE_DEDUCTION => 'danger',
            self::TYPE_BENEFIT => 'info',
            self::TYPE_TAX => 'warning',
            self::TYPE_EMPLOYER_CONTRIBUTION => 'primary',
            self::TYPE_INFORMATIONAL => 'gray',
            default => 'gray',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function valueTypes(): array
    {
        return [
            self::VALUE_TYPE_FIXED,
            self::VALUE_TYPE_PERCENTAGE,
            self::VALUE_TYPE_FORMULA_PLACEHOLDER,
            self::VALUE_TYPE_MANUAL,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function valueTypeLabels(): array
    {
        return [
            self::VALUE_TYPE_FIXED => 'Fixed',
            self::VALUE_TYPE_PERCENTAGE => 'Percentage',
            self::VALUE_TYPE_FORMULA_PLACEHOLDER => 'Formula Placeholder',
            self::VALUE_TYPE_MANUAL => 'Manual',
        ];
    }

    public function applicabilitySummary(): string
    {
        $applicability = data_get($this->metadata, 'applicability', []);
        $labels = [];

        $employmentTypes = array_values(array_filter((array) data_get($applicability, 'employment_type_codes', [])));
        $employmentStatuses = array_values(array_filter((array) data_get($applicability, 'employment_status_codes', [])));
        $contractTypes = array_values(array_filter((array) data_get($applicability, 'contract_type_codes', [])));
        $payrollSchemes = array_values(array_filter((array) data_get($applicability, 'payroll_schemes', [])));
        $employeeGroups = array_values(array_filter((array) data_get($applicability, 'employee_groups', [])));

        if ($employmentTypes !== []) {
            $labels[] = 'Types: '.implode(', ', $employmentTypes);
        }

        if ($employmentStatuses !== []) {
            $labels[] = 'Statuses: '.implode(', ', $employmentStatuses);
        }

        if ($contractTypes !== []) {
            $labels[] = 'Contracts: '.implode(', ', $contractTypes);
        }

        if ($payrollSchemes !== []) {
            $labels[] = 'Schemes: '.implode(', ', $payrollSchemes);
        }

        if ($employeeGroups !== []) {
            $labels[] = 'Groups: '.implode(', ', $employeeGroups);
        }

        foreach ([
            'expatriate_applicable' => 'Expatriate',
            'daily_worker_applicable' => 'Daily Worker',
            'intern_applicable' => 'Intern',
            'probation_applicable' => 'Probation',
            'part_time_applicable' => 'Part Time',
        ] as $key => $label) {
            if ((bool) data_get($applicability, $key, false)) {
                $labels[] = $label;
            }
        }

        return $labels === []
            ? 'All employees'
            : implode(' | ', $labels);
    }
}
