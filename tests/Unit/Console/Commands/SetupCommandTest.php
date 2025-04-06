<?php

declare(strict_types=1);

use App\Console\Commands\SetupCommand;
use Barryvdh\LaravelIdeHelper\Console\EloquentCommand as LaravelIdeHelperEloquentCommand;
use Barryvdh\LaravelIdeHelper\Console\GeneratorCommand as LaravelIdeHelperGeneratorCommand;
use Barryvdh\LaravelIdeHelper\Console\MetaCommand as LaravelIdeHelperMetaCommand;
use Barryvdh\LaravelIdeHelper\Console\ModelsCommand as LaravelIdeHelperModelsCommand;
use Barryvdh\LaravelIdeHelper\Generator as LaravelIdeHelperGenerator;
use Filament\Support\Commands\UpgradeCommand as FilamentUpgradeCommand;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Mockery as m;
use phpmock\phpunit\PHPMock;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

covers(SetupCommand::class);

uses(PHPMock::class);

dataset('envs', [
    'production',
    'staging',
    'development',
    'testing',
    'local',
]);

dataset('class existence', [
    true,
    false,
]);

beforeEach(function (): void {
    $this->setupCommand = new SetupCommand;
    $this->setupCommand->getDefinition()->addOption(
        new InputOption('env', null, InputOption::VALUE_OPTIONAL)
    );

    $this->vendorTags = new ReflectionClass($this->setupCommand)
        ->getProperty('vendorTags')
        ->getValue($this->setupCommand);

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
    it('executes setup command', function (string $env, bool $classExists): void {
        $this->getFunctionMock('App\Console\Commands', 'class_exists')
            ->expects($this->any())
            ->with(LaravelIdeHelperGenerator::class)
            ->willReturn($classExists);

        $this->filamentUpgradeMock->shouldReceive('run')->once()->andReturn(Command::SUCCESS);

        foreach ($this->vendorTags as $tag) {
            $this->vendorPublishMock->shouldReceive('run')
                ->once()
                ->with(
                    m::on(fn ($input): bool => str_contains((string) $input, '--tag='.$tag) && str_contains((string) $input, '--force=1')),
                    m::any()
                )
                ->andReturn(Command::SUCCESS);
        }

        $artisan = $this->artisan(SetupCommand::class, ['--env' => $env])
            ->assertSuccessful()
            ->expectsOutputToContain('INFO  Setup application.')
            ->expectsOutputToContain('Filament upgrade');

        foreach ($this->vendorTags as $tag) {
            $artisan->expectsOutputToContain(sprintf('Publishing assets for tag: [%s]', $tag));
        }

        if ($env === 'local' && $classExists) {
            $this->ideHelperMetaMock->shouldReceive('run')->once()->andReturn(Command::SUCCESS);
            $this->ideHelperGeneratorMock->shouldReceive('run')->once()->andReturn(Command::SUCCESS);
            $this->ideHelperModelsMock->shouldReceive('run')
                ->once()
                ->with(m::on(fn ($input): bool => str_contains((string) $input, '--write-mixin=1')), m::any())
                ->andReturn(Command::SUCCESS);
            $this->ideHelperEloquentMock->shouldReceive('run')->once()->andReturn(Command::SUCCESS);

            $artisan->expectsOutputToContain('Generating IDE helper files');
        } else {
            $this->ideHelperMetaMock->shouldNotReceive('run');
            $this->ideHelperGeneratorMock->shouldNotReceive('run');
            $this->ideHelperModelsMock->shouldNotReceive('run');
            $this->ideHelperEloquentMock->shouldNotReceive('run');

            $artisan->doesntExpectOutput('Generating IDE helper files');
        }
    })->with('envs')->with('class existence');

    it('calls newLine method exactly once', function (string $env): void {
        $input = new ArrayInput(['--env' => $env], $this->setupCommand->getDefinition());
        $outputSpy = m::spy(new OutputStyle($input, new BufferedOutput));
        $this->setupCommand->run($input, $outputSpy);
        $outputSpy->shouldHaveReceived('newLine')->once();
    })->with('envs');
});

afterEach(fn () => m::close());
