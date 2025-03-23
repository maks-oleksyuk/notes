<?php

declare(strict_types=1);

use App\Console\Commands\SetupCommand;

// todo: Add mutate tests.
// covers(SetupCommand::class);

describe('Setup Command', function (): void {
    it('executes setup command in `:dataset` environment', function (string $env): void {
        $this->artisan(SetupCommand::class, ['--env' => $env])
            ->expectsOutputToContain('INFO  Setup application.')
            ->expectsOutputToContain('Filament upgrade')
            ->expectsOutputToContain('Publishing assets for tag: [public]')
            ->expectsOutputToContain('Publishing assets for tag: [laravel-assets]')
            ->expectsOutputToContain('Publishing assets for tag: [log-viewer-assets]')
            ->doesntExpectOutput('Generating IDE helper files')
            ->assertSuccessful();
    })->with([
        'production' => 'production',
        'staging' => 'staging',
        'development' => 'development',
    ]);

    it('executes setup command in `"local"` environment', function (): void {
        $this->artisan(SetupCommand::class, ['--env' => 'local'])
            ->expectsOutputToContain('INFO  Setup application.')
            ->expectsOutputToContain('Filament upgrade')
            ->expectsOutputToContain('Publishing assets for tag: [public]')
            ->expectsOutputToContain('Publishing assets for tag: [laravel-assets]')
            ->expectsOutputToContain('Publishing assets for tag: [log-viewer-assets]')
            ->expectsOutputToContain('Generating IDE helper files')
            ->assertSuccessful();
    });
});
