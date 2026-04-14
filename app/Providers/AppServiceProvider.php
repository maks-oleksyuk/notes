<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Scribe;
use Symfony\Component\HttpFoundation\Request;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureCommands();
        $this->configureDates();
        $this->configureFormRequest();
        $this->configureModels();
        $this->configurePassword();
        $this->configureUrls();
        $this->configureScribeDocumentation();

        $this->publishes([
            $this->app->resourcePath('icons/favicon.ico') => $this->app->publicPath('favicon.ico'),
        ], 'public');

        View::addNamespace(
            'errors',
            base_path('vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views')
        );
    }

    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configureFormRequest(): void
    {
        FormRequest::failOnUnknownFields(! $this->app->isProduction());
    }

    private function configureModels(): void
    {
        Model::shouldBeStrict();
    }

    private function configurePassword(): void
    {
        Password::defaults(fn () => Password::min(8)
            ->numbers()
            ->symbols()
            ->mixedCase()
            ->uncompromised());
    }

    private function configureUrls(): void
    {
        URL::forceHttps($this->app->isProduction());
    }

    private function configureScribeDocumentation(): void
    {
        if (class_exists(Scribe::class)) {
            Scribe::beforeResponseCall(function (Request $request, ExtractedEndpointData $endpointData): void {
                $user = User::factory()->create();
                $token = $user->createToken('api_docs')->plainTextToken;
                $request->headers->add(['Authorization' => 'Bearer '.$token]);
            });
        }
    }
}
