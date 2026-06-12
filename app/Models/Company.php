<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    public const DEFAULT_CODE = 'DEFAULT';

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'email',
        'phone',
        'address',
        'tax_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function defaultAttributes(): array
    {
        return [
            'code' => self::DEFAULT_CODE,
            'name' => 'Default Company',
            'legal_name' => 'Default Company',
            'is_active' => true,
        ];
    }

    public static function findOrCreateDefault(): self
    {
        $company = static::withTrashed()->firstOrCreate(
            ['code' => self::DEFAULT_CODE],
            self::defaultAttributes()
        );

        if ($company->trashed()) {
            $company->restore();
        }

        return $company;
    }

    public static function getDefaultCompanyId(): ?int
    {
        return static::query()
            ->where('code', self::DEFAULT_CODE)
            ->value('id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function workLocations(): HasMany
    {
        return $this->hasMany(WorkLocation::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
