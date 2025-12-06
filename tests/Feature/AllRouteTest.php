<?php

declare(strict_types=1);

use App\Models\User;
use function Spatie\RouteTesting\routeTesting;

// Smoke test for public web routes only.
routeTesting('public web routes are accessible')
    ->exclude([
        // vendor/internal routes (don't need testing)
        '_debugbar*',
        'livewire*',
        'logs*',
        'pulse*',
        'sanctum*',
        'storage*',
        'filament*',
        'up',

        // Routes with dedicated feature tests
        'api*',
        'admin*',
        'docs*',
    ])
    ->bind('user', fn () => User::factory()->create())
    ->assertSuccessful();
