<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\TwigConfig;

return static function (ContainerConfigurator $containerConfigurator, TwigConfig $twigConfig): void {
    $twigConfig->fileNamePattern('*.twig');

    if ('test' === $containerConfigurator->env()) {
        $twigConfig->strictVariables(true);
    }
};
