<?php

namespace App\Filament\Resources\LeavePolicies\Pages;

use App\Filament\Resources\LeavePolicies\LeavePolicyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeavePolicy extends CreateRecord
{
    protected static string $resource = LeavePolicyResource::class;
}
