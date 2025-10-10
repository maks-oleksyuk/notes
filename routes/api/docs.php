<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

$configFiles = ['scribe-v1'];

foreach ($configFiles as $config) {
    $prefix = config($config.'.laravel.docs_url', '/docs');
    $middleware = config($config.'.laravel.middleware', []);

    Route::middleware($middleware)->group(function () use ($config, $prefix): void {
        Route::view($prefix, $config.'.index')->name($config.'.docs');

        Route::get($prefix.'.postman', fn (): JsonResponse => new JsonResponse(Illuminate\Support\Facades\Storage::disk('local')->get($config.'/collection.json'), json: true))->name($config.'.postman');

        Route::get($prefix.'.openapi', fn () => response()->file(Illuminate\Support\Facades\Storage::disk('local')->path($config.'/openapi.yaml')))->name($config.'.openapi');
    });
}
