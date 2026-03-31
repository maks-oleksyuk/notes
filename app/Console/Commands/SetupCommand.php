<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Barryvdh\LaravelIdeHelper\Console\EloquentCommand as LaravelIdeHelperEloquentCommand;
use Barryvdh\LaravelIdeHelper\Console\GeneratorCommand as LaravelIdeHelperGeneratorCommand;
use Barryvdh\LaravelIdeHelper\Console\MetaCommand as LaravelIdeHelperMetaCommand;
use Barryvdh\LaravelIdeHelper\Console\ModelsCommand as LaravelIdeHelperModelsCommand;
use Barryvdh\LaravelIdeHelper\Generator as LaravelIdeHelperGenerator;
use Filament\Support\Commands\UpgradeCommand as FilamentUpgradeCommand;
use Illuminate\Console\Command;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:setup', description: 'Setup application')]
final class SetupCommand extends Command
{
    /**
     * @var list<string>
     */
    private array $vendorTags = [
        'public',
        'laravel-assets',
        'scribe-external',
    ];

    public function handle(): int
    {
        $env = $this->option('env') ?: $this->laravel->environment();

        $this->components->info('Setup application');

        $this->components->task('Filament upgrade',
            fn () => $this->callSilently(FilamentUpgradeCommand::class)
        );

        foreach ($this->vendorTags as $vendorTag) {
            $this->components->task(sprintf('Publishing assets for tag: [%s]', $vendorTag),
                fn () => $this->callSilently(VendorPublishCommand::class, [
                    '--tag' => $vendorTag,
                    '--force' => true,
                ])
            );
        }

        if ($env === 'local' && class_exists(LaravelIdeHelperGenerator::class)) {
            $this->components->task('Generating IDE helper files', function (): void {
                $this->callSilently(LaravelIdeHelperMetaCommand::class);
                $this->callSilently(LaravelIdeHelperGeneratorCommand::class);
                $this->callSilently(LaravelIdeHelperModelsCommand::class, ['--write-mixin' => true]);
                $this->callSilently(LaravelIdeHelperEloquentCommand::class);
            });
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
