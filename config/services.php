<?php

declare(strict_types=1);

use App\EventListener\MaintenanceKernelRequestSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Profiler\Profiler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(MAINTENANCE_MODE)', false);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\', __DIR__.'/../src/');

    // Event listeners.
    $services->set(MaintenanceKernelRequestSubscriber::class)
        ->args([env('MAINTENANCE_MODE')->bool()]);

    if ('dev' === $containerConfigurator->env()) {
        $services->alias(Profiler::class, 'profiler');
    }
};
