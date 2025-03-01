<?php

declare(strict_types=1);

use App\Console\Commands\Setup;

covers(Setup::class);

describe('Setup Command', function () {
    it('executes setup command in `non-local` environment', function (string $env): void {
        $this->artisan('app:setup', ['--env' => $env])
            ->expectsOutputToContain('Filament upgrade')
            ->expectsOutputToContain('Vendor publish')
            ->doesntExpectOutput('Generating IDE helper files')
            ->assertExitCode(0);
    })->with(['production', 'staging', 'development']);

    it('executes setup command in `local` environment', function (): void {
        $this->artisan('app:setup', ['--env' => 'local'])
            ->expectsOutputToContain('Filament upgrade')
            ->expectsOutputToContain('Vendor publish')
            ->expectsOutputToContain('Generating IDE helper files')
            ->assertExitCode(0);
    });
});
