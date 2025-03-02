<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void {}

    public function boot(): void
    {
        $this->configureCommands();
        $this->configureModels();
        $this->configureUrls();

        $this->publishes([
            __DIR__.'/../../resources/icons/favicon.ico' => public_path('favicon.ico'),
        ], 'public');
    }

    protected function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    protected function configureModels(): void
    {
        Model::shouldBeStrict();
        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();
        Model::preventSilentlyDiscardingAttributes();
    }

    protected function configureUrls(): void
    {
        URL::forceHttps($this->app->isProduction());
    }
}
