<?php

declare(strict_types=1);

use Symfony\Config\FrameworkConfig;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (FrameworkConfig $config): void {
    $config->defaultLocale('en');

    $config
        ->translator()
        ->defaultPath(param('kernel.project_dir').'/translations');
};
