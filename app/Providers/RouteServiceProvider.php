<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

final class RouteServiceProvider extends ServiceProvider
{
    #[\Override]
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureApiRoutes();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for(
            'api',
            fn (Request $request) => Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            )
        );
    }

    private function configureApiRoutes(): void
    {
        $this->routes(function (): void {
            Route::group([], base_path('routes/api/docs.php'));

            Route::prefix('api/v1')
                ->as('api.v1.')
                ->middleware(['api', 'auth:sanctum'])
                ->group(base_path('routes/api/v1.php'));
        });
    }
}
