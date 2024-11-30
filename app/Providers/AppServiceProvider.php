<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../resources/icons/favicon.ico' => public_path('favicon.ico'),
        ], 'public');
    }
}
