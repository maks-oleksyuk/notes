<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\Framework\SessionConfig;
use Symfony\Config\FrameworkConfig;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return static function (ContainerConfigurator $containerConfigurator, FrameworkConfig $frameworkConfig): void {
    $sessionConfig = $frameworkConfig->session();
    assert($sessionConfig instanceof SessionConfig);

    $frameworkConfig->secret(env('APP_SECRET'));

    if ('test' === $containerConfigurator->env()) {
        $frameworkConfig->test(true);
        $sessionConfig->storageFactoryId('session.storage.factory.mock_file');
    }
};
