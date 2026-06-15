<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Position extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'title',
        'branch_id',
        'department_id',
        'division_id',
        'job_level_id',
        'job_grade_id',
        'code',
        'description',
        'salary',
        'is_active',
    ];

    protected $table = 'positions';

    protected $with = ['department'];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'department_id' => 'integer',
        'division_id' => 'integer',
        'job_level_id' => 'integer',
        'job_grade_id' => 'integer',
        'salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $position): void {
            if (filled($position->department_id)) {
                $department = Department::query()->find($position->department_id);

                if (! $department) {
                    throw ValidationException::withMessages([
                        'department_id' => 'The selected department is invalid.',
                    ]);
                }

                $position->company_id ??= $department->company_id;

                if (filled($position->company_id) && (int) $position->company_id !== (int) $department->company_id) {
                    throw ValidationException::withMessages([
                        'department_id' => 'The selected department must belong to the selected company.',
                    ]);
                }
            }

            if (filled($position->branch_id)) {
                $branchCompanyId = Branch::query()->whereKey($position->branch_id)->value('company_id');

                if (filled($branchCompanyId) && (int) $branchCompanyId !== (int) $position->company_id) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'The selected branch must belong to the selected company.',
                    ]);
                }
            }

            if (filled($position->division_id)) {
                $division = Division::query()->find($position->division_id);

                if (! $division) {
                    throw ValidationException::withMessages([
                        'division_id' => 'The selected division is invalid.',
                    ]);
                }

                if (filled($position->company_id) && (int) $division->company_id !== (int) $position->company_id) {
                    throw ValidationException::withMessages([
                        'division_id' => 'The selected division must belong to the selected company.',
                    ]);
                }

                if (filled($position->department_id) && filled($division->department_id) && (int) $division->department_id !== (int) $position->department_id) {
                    throw ValidationException::withMessages([
                        'division_id' => 'The selected division must belong to the selected department.',
                    ]);
                }
            }

            $companyGroupId = Company::query()
                ->whereKey($position->company_id)
                ->value('company_group_id');

            $position->validateScopedMasterReference(JobLevel::class, $position->job_level_id, 'job_level_id', $companyGroupId);
            $position->validateScopedMasterReference(JobGrade::class, $position->job_grade_id, 'job_grade_id', $companyGroupId);
        });
    }

    protected function resolveCompanyIdForCreation(): ?int
    {
        if (filled($this->department_id)) {
            return Department::query()->whereKey($this->department_id)->value('company_id');
        }

        return null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class);
    }

    public function jobGrade(): BelongsTo
    {
        return $this->belongsTo(JobGrade::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    private function validateScopedMasterReference(string $modelClass, ?int $recordId, string $field, ?int $companyGroupId): void
    {
        if (blank($recordId)) {
            return;
        }

        /** @var \App\Models\MasterData|null $record */
        $record = $modelClass::query()->find($recordId);

        if (! $record || ! $record->isAvailableFor($this->company_id, $companyGroupId)) {
            throw ValidationException::withMessages([
                $field => 'The selected value is outside the allowed company or company group scope.',
            ]);
        }
    }
}
