<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class RouteServiceProvider extends ServiceProvider
{
    #[\Override]
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureApiDocsRoutes();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for(
            'login',
            fn (Request $request) => Limit::perMinute(5)->by(
                Str::lower($request->string('email', '')->toString()).'|'.$request->ip()
            )
        );

        RateLimiter::for(
            'api',
            fn (Request $request) => Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            )
        );
    }

    private function configureApiDocsRoutes(): void
    {
        $this->routes(function (): void {
            Route::group([], base_path('routes/api/docs.php'));
        });
    }
}
