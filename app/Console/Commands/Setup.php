<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Barryvdh\LaravelIdeHelper\Generator as LaravelIdeHelperGenerator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:setup', description: 'Setup application')]
final class Setup extends Command
{
    public function handle(): int
    {
        $env = $this->option('env') ?: $this->laravel->environment();

        $this->components->task('Filament upgrade',
            fn () => $this->callSilently('filament:upgrade')
        );

        $this->components->task('Vendor publish',
            fn () => $this->callSilently('vendor:publish', [
                '--tag' => ['laravel-assets', 'public'],
                '--force' => true,
            ])
        );

        if ($env === 'local' && class_exists(LaravelIdeHelperGenerator::class)) {
            $this->components->task('Generating IDE helper files', function (): void {
                $this->callSilently('ide-helper:meta');
                $this->callSilently('ide-helper:generate');
                $this->callSilently('ide-helper:models', ['--write-mixin' => true]);
                $this->callSilently('ide-helper:eloquent');
            });
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
