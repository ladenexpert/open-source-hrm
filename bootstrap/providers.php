<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\EmployeePanelProvider;
use Spatie\Permission\PermissionServiceProvider;

return [
    AuthServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    EmployeePanelProvider::class,
    PermissionServiceProvider::class,
];
