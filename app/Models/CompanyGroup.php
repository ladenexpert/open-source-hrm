<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyGroup extends Model
{
    public const DEFAULT_CODE = 'DEFAULT-GROUP';

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function defaultAttributes(): array
    {
        return [
            'code' => self::DEFAULT_CODE,
            'name' => 'Default Company Group',
            'legal_name' => 'Default Company Group',
            'description' => 'Starter holding structure for the default tenant.',
            'is_active' => true,
        ];
    }

    public static function findOrCreateDefault(): self
    {
        return static::query()->firstOrCreate(
            ['code' => self::DEFAULT_CODE],
            self::defaultAttributes(),
        );
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
