<?php

declare(strict_types=1);

use App\Console\Commands\Setup;

covers(Setup::class);

describe('Setup Command', function (): void {
    it('executes setup command in `non-local` environment', function (string $env): void {
        $this->artisan('app:setup', ['--env' => $env])
            ->expectsOutputToContain('INFO  Setup application.')
            ->expectsOutputToContain('Filament upgrade')
            ->expectsOutputToContain('Publishing assets for tag: [laravel-assets]')
            ->expectsOutputToContain('Publishing assets for tag: [public]')
            ->doesntExpectOutput('Generating IDE helper files')
            ->assertExitCode(0);
    })->with(['production', 'staging', 'development']);

    it('executes setup command in `local` environment', function (): void {
        $this->artisan('app:setup', ['--env' => 'local'])
            ->expectsOutputToContain('INFO  Setup application.')
            ->expectsOutputToContain('Filament upgrade')
            ->expectsOutputToContain('Publishing assets for tag: [laravel-assets]')
            ->expectsOutputToContain('Publishing assets for tag: [public]')
            ->expectsOutputToContain('Generating IDE helper files')
            ->assertExitCode(0);
    });
});
