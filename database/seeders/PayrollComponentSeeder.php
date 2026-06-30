<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PayrollComponent;
use Illuminate\Database\Seeder;

class PayrollComponentSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', Company::DEFAULT_CODE)->first();

        if (! $company instanceof Company) {
            return;
        }

        $components = [
            [
                'component_code' => 'BASIC_SALARY',
                'name' => 'Basic Salary',
                'description' => 'Core earning component placeholder for base salary assignment.',
                'component_type' => PayrollComponent::TYPE_EARNING,
                'value_type' => PayrollComponent::VALUE_TYPE_FIXED,
                'taxable' => true,
                'proratable' => true,
                'recurring' => true,
                'sort_order' => 10,
                'metadata' => [
                    'applicability' => [
                        'employment_type_codes' => ['PKWTT', 'PKWT'],
                    ],
                ],
            ],
            [
                'component_code' => 'FIXED_ALLOWANCE',
                'name' => 'Fixed Allowance',
                'description' => 'Allowance placeholder without calculation logic.',
                'component_type' => PayrollComponent::TYPE_EARNING,
                'value_type' => PayrollComponent::VALUE_TYPE_FIXED,
                'taxable' => true,
                'recurring' => true,
                'sort_order' => 20,
            ],
            [
                'component_code' => 'OVERTIME_ALLOWANCE',
                'name' => 'Overtime Allowance Placeholder',
                'description' => 'Placeholder for future overtime-related earnings.',
                'component_type' => PayrollComponent::TYPE_EARNING,
                'value_type' => PayrollComponent::VALUE_TYPE_FORMULA_PLACEHOLDER,
                'taxable' => true,
                'sort_order' => 30,
            ],
            [
                'component_code' => 'UNPAID_LEAVE_DEDUCTION',
                'name' => 'Unpaid Leave Deduction Placeholder',
                'description' => 'Deduction placeholder for future unpaid leave rules.',
                'component_type' => PayrollComponent::TYPE_DEDUCTION,
                'value_type' => PayrollComponent::VALUE_TYPE_FORMULA_PLACEHOLDER,
                'tax_deductible' => false,
                'sort_order' => 40,
            ],
            [
                'component_code' => 'PPH21_PLACEHOLDER',
                'name' => 'PPh21 Tax Placeholder',
                'description' => 'Tax classification placeholder without calculation logic.',
                'component_type' => PayrollComponent::TYPE_TAX,
                'value_type' => PayrollComponent::VALUE_TYPE_FORMULA_PLACEHOLDER,
                'sort_order' => 50,
            ],
            [
                'component_code' => 'BPJS_EMPLOYEE_DEDUCTION',
                'name' => 'BPJS Employee Deduction Placeholder',
                'description' => 'Deduction placeholder for employee BPJS treatment.',
                'component_type' => PayrollComponent::TYPE_DEDUCTION,
                'value_type' => PayrollComponent::VALUE_TYPE_PERCENTAGE,
                'default_percentage' => '1.0000',
                'bpjs_applicable' => true,
                'sort_order' => 60,
            ],
            [
                'component_code' => 'BPJS_EMPLOYER_CONTRIBUTION',
                'name' => 'BPJS Employer Contribution Placeholder',
                'description' => 'Employer-paid contribution placeholder without posting logic.',
                'component_type' => PayrollComponent::TYPE_EMPLOYER_CONTRIBUTION,
                'value_type' => PayrollComponent::VALUE_TYPE_PERCENTAGE,
                'default_percentage' => '4.0000',
                'bpjs_applicable' => true,
                'sort_order' => 70,
            ],
            [
                'component_code' => 'DAILY_WAGE',
                'name' => 'Daily Wage Placeholder',
                'description' => 'Daily worker placeholder without attendance-based calculation.',
                'component_type' => PayrollComponent::TYPE_EARNING,
                'value_type' => PayrollComponent::VALUE_TYPE_MANUAL,
                'sort_order' => 80,
                'metadata' => [
                    'applicability' => [
                        'employment_type_codes' => ['DAILY_WORKER'],
                        'contract_type_codes' => ['DAILY_WORKER'],
                        'daily_worker_applicable' => true,
                    ],
                ],
            ],
            [
                'component_code' => 'INTERN_STIPEND',
                'name' => 'Intern Stipend Placeholder',
                'description' => 'Intern stipend placeholder without payroll calculation.',
                'component_type' => PayrollComponent::TYPE_BENEFIT,
                'value_type' => PayrollComponent::VALUE_TYPE_FIXED,
                'sort_order' => 90,
                'metadata' => [
                    'applicability' => [
                        'employment_type_codes' => ['INTERN'],
                        'contract_type_codes' => ['INTERNSHIP_AGREEMENT'],
                        'intern_applicable' => true,
                    ],
                ],
            ],
            [
                'component_code' => 'EXPATRIATE_ALLOWANCE',
                'name' => 'Expatriate Allowance Placeholder',
                'description' => 'Expatriate allowance placeholder without split-payroll calculation.',
                'component_type' => PayrollComponent::TYPE_BENEFIT,
                'value_type' => PayrollComponent::VALUE_TYPE_MANUAL,
                'sort_order' => 100,
                'metadata' => [
                    'applicability' => [
                        'employment_type_codes' => ['EXPATRIATE'],
                        'contract_type_codes' => ['EXPAT_ASSIGNMENT'],
                        'payroll_schemes' => ['split_payroll'],
                        'expatriate_applicable' => true,
                    ],
                ],
            ],
            [
                'component_code' => 'PRODUCTION_ALLOWANCE',
                'name' => 'Production Allowance Placeholder',
                'description' => 'Informational or manual placeholder for production manpower support.',
                'component_type' => PayrollComponent::TYPE_INFORMATIONAL,
                'value_type' => PayrollComponent::VALUE_TYPE_MANUAL,
                'sort_order' => 110,
                'metadata' => [
                    'applicability' => [
                        'daily_worker_applicable' => true,
                        'employee_groups' => ['production_manpower'],
                    ],
                ],
            ],
        ];

        foreach ($components as $component) {
            PayrollComponent::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'component_code' => $component['component_code'],
                ],
                array_merge([
                    'description' => null,
                    'default_amount' => null,
                    'default_percentage' => null,
                    'taxable' => false,
                    'tax_deductible' => false,
                    'bpjs_applicable' => false,
                    'thr_applicable' => false,
                    'proratable' => false,
                    'recurring' => true,
                    'active' => true,
                    'sort_order' => 0,
                    'metadata' => null,
                ], $component)
            );
        }
    }
}
