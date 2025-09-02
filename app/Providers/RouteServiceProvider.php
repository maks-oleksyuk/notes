<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class RouteServiceProvider extends ServiceProvider
{
    #[\Override]
    public function boot(): void
    {
        $this->routes(function (): void {
            Route::prefix('api/v1')
                ->as('api.v1.')
                ->middleware('api')
                ->group(base_path('routes/api/v1.php'));
        });
    }
}
