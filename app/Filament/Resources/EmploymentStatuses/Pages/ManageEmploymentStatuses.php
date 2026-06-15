<?php

namespace App\Filament\Resources\EmploymentStatuses\Pages;

use App\Filament\Resources\EmploymentStatuses\EmploymentStatusResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmploymentStatuses extends ManageRecords
{
    protected static string $resource = EmploymentStatusResource::class;
}
