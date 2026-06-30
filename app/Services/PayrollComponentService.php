<?php

namespace App\Services;

use App\Models\PayrollComponent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayrollComponentService
{
    public function validateAndNormalize(PayrollComponent $component): void
    {
        $component->component_code = $this->normalizeCode($component->component_code);
        $component->name = trim((string) $component->name);
        $component->description = $this->normalizeNullableString($component->description);
        $component->metadata = $this->normalizeMetadata($component->metadata);

        $this->validateCompanyScope($component);
        $this->validateComponentType($component);
        $this->validateValueType($component);
        $this->validateValueConfiguration($component);
        $this->validateCodeUniqueness($component);
    }

    private function validateCompanyScope(PayrollComponent $component): void
    {
        if (blank($component->company_id)) {
            throw ValidationException::withMessages([
                'company_id' => 'The payroll component must belong to a company.',
            ]);
        }
    }

    private function validateComponentType(PayrollComponent $component): void
    {
        if (! in_array($component->component_type, PayrollComponent::componentTypes(), true)) {
            throw ValidationException::withMessages([
                'component_type' => 'The selected payroll component type is invalid.',
            ]);
        }
    }

    private function validateValueType(PayrollComponent $component): void
    {
        if (! in_array($component->value_type, PayrollComponent::valueTypes(), true)) {
            throw ValidationException::withMessages([
                'value_type' => 'The selected payroll component value type is invalid.',
            ]);
        }
    }

    private function validateValueConfiguration(PayrollComponent $component): void
    {
        $amount = $component->default_amount;
        $percentage = $component->default_percentage;

        if ($amount !== null && (float) $amount < 0) {
            throw ValidationException::withMessages([
                'default_amount' => 'The default amount must be zero or greater.',
            ]);
        }

        if ($percentage !== null && (float) $percentage < 0) {
            throw ValidationException::withMessages([
                'default_percentage' => 'The default percentage must be zero or greater.',
            ]);
        }

        if ($component->value_type === PayrollComponent::VALUE_TYPE_PERCENTAGE && blank($percentage)) {
            throw ValidationException::withMessages([
                'default_percentage' => 'Percentage payroll components require a default percentage placeholder.',
            ]);
        }

        if (
            in_array($component->value_type, [
                PayrollComponent::VALUE_TYPE_FIXED,
                PayrollComponent::VALUE_TYPE_MANUAL,
            ], true)
            && filled($percentage)
        ) {
            throw ValidationException::withMessages([
                'default_percentage' => 'Only percentage payroll components can store a default percentage.',
            ]);
        }

        if (
            $component->value_type === PayrollComponent::VALUE_TYPE_PERCENTAGE
            && filled($amount)
        ) {
            throw ValidationException::withMessages([
                'default_amount' => 'Percentage payroll components cannot store a default amount.',
            ]);
        }

        if (
            $component->value_type === PayrollComponent::VALUE_TYPE_FORMULA_PLACEHOLDER
            && (filled($amount) || filled($percentage))
        ) {
            throw ValidationException::withMessages([
                'value_type' => 'Formula placeholder payroll components cannot store fixed or percentage defaults yet.',
            ]);
        }
    }

    private function validateCodeUniqueness(PayrollComponent $component): void
    {
        if (blank($component->component_code)) {
            return;
        }

        $exists = PayrollComponent::query()
            ->where('company_id', $component->company_id)
            ->where('component_code', $component->component_code)
            ->when(
                $component->exists,
                fn ($query) => $query->whereKeyNot($component->getKey()),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'component_code' => 'The component code must be unique for the company.',
            ]);
        }
    }

    private function normalizeCode(mixed $code): ?string
    {
        $code = $this->normalizeNullableString($code);

        if ($code === null) {
            return null;
        }

        return Str::upper(Str::replace(' ', '_', $code));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $normalized = Arr::except($metadata, ['applicability']);
        $applicability = [
            'employment_status_codes' => $this->normalizeStringList(data_get($metadata, 'applicability.employment_status_codes', [])),
            'employment_type_codes' => $this->normalizeStringList(data_get($metadata, 'applicability.employment_type_codes', [])),
            'contract_type_codes' => $this->normalizeStringList(data_get($metadata, 'applicability.contract_type_codes', [])),
            'payroll_schemes' => $this->normalizeStringList(data_get($metadata, 'applicability.payroll_schemes', [])),
            'employee_groups' => $this->normalizeStringList(data_get($metadata, 'applicability.employee_groups', [])),
            'expatriate_applicable' => (bool) data_get($metadata, 'applicability.expatriate_applicable', false),
            'daily_worker_applicable' => (bool) data_get($metadata, 'applicability.daily_worker_applicable', false),
            'intern_applicable' => (bool) data_get($metadata, 'applicability.intern_applicable', false),
            'probation_applicable' => (bool) data_get($metadata, 'applicability.probation_applicable', false),
            'part_time_applicable' => (bool) data_get($metadata, 'applicability.part_time_applicable', false),
        ];

        $hasApplicabilityValues = collect($applicability)->contains(function (mixed $value): bool {
            if (is_array($value)) {
                return $value !== [];
            }

            return $value === true;
        });

        if ($hasApplicabilityValues) {
            $normalized['applicability'] = $applicability;
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param  mixed  $values
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $values): array
    {
        return collect(is_array($values) ? $values : [])
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
