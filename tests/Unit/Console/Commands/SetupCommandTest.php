<?php

declare(strict_types=1);

use App\Console\Commands\SetupCommand;
use Barryvdh\LaravelIdeHelper\Console\EloquentCommand as LaravelIdeHelperEloquentCommand;
use Barryvdh\LaravelIdeHelper\Console\GeneratorCommand as LaravelIdeHelperGeneratorCommand;
use Barryvdh\LaravelIdeHelper\Console\MetaCommand as LaravelIdeHelperMetaCommand;
use Barryvdh\LaravelIdeHelper\Console\ModelsCommand as LaravelIdeHelperModelsCommand;
use Filament\Support\Commands\UpgradeCommand as FilamentUpgradeCommand;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Mockery as m;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

covers(SetupCommand::class);

beforeEach(function (): void {
    $this->vendorTag = ['public', 'laravel-assets', 'log-viewer-assets'];

    $this->setupCommand = new SetupCommand;
    $this->setupCommand->getDefinition()->addOption(
        new InputOption('env', null, InputOption::VALUE_OPTIONAL)
    );

    $this->filamentUpgradeMock = $this->mock(FilamentUpgradeCommand::class)->shouldIgnoreMissing();
    $this->vendorPublishMock = $this->mock(VendorPublishCommand::class)->shouldIgnoreMissing();
    $this->ideHelperMetaMock = $this->mock(LaravelIdeHelperMetaCommand::class)->shouldIgnoreMissing();
    $this->ideHelperGeneratorMock = $this->mock(LaravelIdeHelperGeneratorCommand::class)->shouldIgnoreMissing();
    $this->ideHelperModelsMock = $this->mock(LaravelIdeHelperModelsCommand::class)->shouldIgnoreMissing();
    $this->ideHelperEloquentMock = $this->mock(LaravelIdeHelperEloquentCommand::class)->shouldIgnoreMissing();

    $this->app->instance(FilamentUpgradeCommand::class, $this->filamentUpgradeMock);
    $this->app->instance(VendorPublishCommand::class, $this->vendorPublishMock);
    $this->app->instance(LaravelIdeHelperMetaCommand::class, $this->ideHelperMetaMock);
    $this->app->instance(LaravelIdeHelperGeneratorCommand::class, $this->ideHelperGeneratorMock);
    $this->app->instance(LaravelIdeHelperModelsCommand::class, $this->ideHelperModelsMock);
    $this->app->instance(LaravelIdeHelperEloquentCommand::class, $this->ideHelperEloquentMock);

    $this->console = new ConsoleApplication;
    $this->console->add($this->setupCommand);

    $this->setupCommand->setLaravel($this->app);
    $this->setupCommand->setApplication($this->console);
});

describe('Setup Command', function (): void {
    it('executes setup command in `:dataset` environment', function (string $env): void {
        $this->filamentUpgradeMock->shouldReceive('run')->once()->andReturn(0);

        foreach ($this->vendorTag as $tag) {
            $this->vendorPublishMock->shouldReceive('run')
                ->once()
                ->with(
                    m::on(fn ($input): bool => str_contains((string) $input, '--tag='.$tag) && str_contains((string) $input, '--force=1')),
                    m::any()
                )
                ->andReturn(0);
        }

        $this->ideHelperMetaMock->shouldNotHaveReceived('run');
        $this->ideHelperGeneratorMock->shouldNotHaveReceived('run');
        $this->ideHelperModelsMock->shouldNotHaveReceived('run');
        $this->ideHelperEloquentMock->shouldNotHaveReceived('run');

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
        'testing' => 'testing',
    ]);

    it('executes setup command in `"local"` environment', function (): void {
        $this->filamentUpgradeMock->shouldReceive('run')->once()->andReturn(0);

        foreach ($this->vendorTag as $tag) {
            $this->vendorPublishMock->shouldReceive('run')
                ->once()
                ->with(
                    m::on(fn ($input): bool => str_contains((string) $input, '--tag='.$tag) && str_contains((string) $input, '--force=1')),
                    m::any()
                )
                ->andReturn(0);
        }

        $this->ideHelperMetaMock->shouldReceive('run')->once()->andReturn(0);
        $this->ideHelperGeneratorMock->shouldReceive('run')->once()->andReturn(0);
        $this->ideHelperModelsMock->shouldReceive('run')
            ->once()
            ->with(m::on(fn ($input): bool => str_contains((string) $input, '--write-mixin=1')), m::any())
            ->andReturn(0);
        $this->ideHelperEloquentMock->shouldReceive('run')->once()->andReturn(0);

        $this->artisan(SetupCommand::class, ['--env' => 'local'])
            ->expectsOutputToContain('INFO  Setup application.')
            ->expectsOutputToContain('Filament upgrade')
            ->expectsOutputToContain('Publishing assets for tag: [public]')
            ->expectsOutputToContain('Publishing assets for tag: [laravel-assets]')
            ->expectsOutputToContain('Publishing assets for tag: [log-viewer-assets]')
            ->expectsOutputToContain('Generating IDE helper files')
            ->assertSuccessful();
    });

    it('calls newLine method exactly once in `:dataset` environment', function (string $env): void {
        $input = new ArrayInput(['--env' => $env]);
        $bufferedOutput = new BufferedOutput;
        $outputStyle = new OutputStyle($input, $bufferedOutput);
        $outputSpy = m::spy($outputStyle);
        $this->setupCommand->run($input, $outputSpy);
        $outputSpy->shouldHaveReceived('newLine')->once();
    })->with([
        'production' => 'production',
        'staging' => 'staging',
        'development' => 'development',
        'testing' => 'testing',
        'local' => 'local',
    ]);
});
