<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\RouteServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelServiceProvider::class,
    RepositoryServiceProvider::class,
    RouteServiceProvider::class,
];
