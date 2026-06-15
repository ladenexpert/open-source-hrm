<?php

namespace App\Filament\Resources\IdentityTypes\Pages;

use App\Filament\Resources\IdentityTypes\IdentityTypeResource;
use Filament\Resources\Pages\ManageRecords;

class ManageIdentityTypes extends ManageRecords
{
    protected static string $resource = IdentityTypeResource::class;
}
