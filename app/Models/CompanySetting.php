<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'key',
        'value',
        'description',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
