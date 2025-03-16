<?php

declare(strict_types=1);

// use App\Console\Commands\Setup;
// todo: Add mutate tests.
// covers(Setup::class);

describe('Setup Command', function (): void {
    it('executes setup command in `:dataset` environment', function (string $env): void {
        $this->artisan('app:setup', ['--env' => $env])
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
        $this->artisan('app:setup', ['--env' => 'local'])
            ->expectsOutputToContain('INFO  Setup application.')
            ->expectsOutputToContain('Filament upgrade')
            ->expectsOutputToContain('Publishing assets for tag: [public]')
            ->expectsOutputToContain('Publishing assets for tag: [laravel-assets]')
            ->expectsOutputToContain('Publishing assets for tag: [log-viewer-assets]')
            ->expectsOutputToContain('Generating IDE helper files')
            ->assertSuccessful();
    });
});
