<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Barryvdh\LaravelIdeHelper\Generator as LaravelIdeHelperGenerator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:setup', description: 'Setup application')]
final class Setup extends Command
{
    /**
     * @var list<string>
     */
    private array $vendorTags = [
        'public',
        'laravel-assets',
        'log-viewer-assets',
    ];

    public function handle(): int
    {
        $env = $this->option('env') ?: $this->laravel->environment();

        $this->components->info('Setup application');

        $this->components->task('Filament upgrade',
            fn () => $this->callSilently('filament:upgrade')
        );

        foreach ($this->vendorTags as $vendorTag) {
            $this->components->task(sprintf('Publishing assets for tag: [%s]', $vendorTag),
                fn () => $this->callSilently('vendor:publish', [
                    '--tag' => $vendorTag,
                    '--force' => true,
                ])
            );
        }

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
