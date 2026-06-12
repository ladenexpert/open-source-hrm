<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'national_id',
        'kra_pin',
        'emergency_contact_name',
        'emergency_contact_phone',
        'date_of_birth',
        'gender',
        'marital_status',
        'department_id',
        'position_id',
        'employment_type',
        'hire_date',
        'termination_date',
        'is_active',
        'next_of_kin_name',
        'next_of_kin_relationship',
        'next_of_kin_phone',
        'next_of_kin_email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $appends = [
        'name',
    ];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->canAccessAdminPanel(),
            'portal' => true,
            default => false,
        };
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->hasAnyNormalizedRole([
            'super_admin',
            'admin',
            'hr',
            'finance',
            'department_manager',
        ]);
    }

    public function canManageHrMasterData(): bool
    {
        return $this->hasAnyNormalizedRole([
            'super_admin',
            'admin',
            'hr',
        ]);
    }

    public function canManagePayroll(): bool
    {
        return $this->hasAnyNormalizedRole([
            'super_admin',
            'finance',
        ]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasNormalizedRole('super_admin');
    }

    public function isDepartmentManager(): bool
    {
        return $this->hasNormalizedRole('department_manager');
    }

    public function hasNormalizedRole(string $role): bool
    {
        $expectedRole = $this->normalizeRoleName($role);

        return $this->getRoleNames()
            ->contains(fn (string $assignedRole): bool => $this->normalizeRoleName($assignedRole) === $expectedRole);
    }

    public function hasAnyNormalizedRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasNormalizedRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function managesDepartment(?int $departmentId): bool
    {
        if (! $this->isDepartmentManager() || blank($departmentId)) {
            return false;
        }

        return $this->managedDepartments()
            ->whereKey($departmentId)
            ->exists();
    }

    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected function normalizeRoleName(string $role): string
    {
        return Str::of($role)
            ->lower()
            ->replace(['-', ' '], '_')
            ->value();
    }
}
