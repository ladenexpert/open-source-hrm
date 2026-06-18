<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ShiftPattern extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'is_overnight',
        'color',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_overnight' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $shiftPattern): void {
            if (filled($shiftPattern->color) && ! preg_match('/^#?[0-9A-Fa-f]{6}$/', $shiftPattern->color)) {
                throw ValidationException::withMessages([
                    'color' => 'The color must be a six-character hex value.',
                ]);
            }

            if (filled($shiftPattern->color) && ! str_starts_with($shiftPattern->color, '#')) {
                $shiftPattern->color = '#'.$shiftPattern->color;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ShiftPatternDetail::class)->orderBy('day_of_week')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function syncOvernightFlag(): void
    {
        $hasOvernightDetail = $this->details()->get()->contains(
            static fn (ShiftPatternDetail $detail): bool => $detail->isOvernight()
        );

        if ($this->is_overnight !== $hasOvernightDetail) {
            $this->forceFill(['is_overnight' => $hasOvernightDetail])->saveQuietly();
        }
    }
}
