<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\Framework\ProfilerConfig;
use Symfony\Config\FrameworkConfig;
use Symfony\Config\WebProfilerConfig;

return static function (
    ContainerConfigurator $containerConfigurator,
    WebProfilerConfig $webProfilerConfig,
    FrameworkConfig $frameworkConfig,
): void {
    $frameworkProfilerConfig = $frameworkConfig->profiler();
    assert($frameworkProfilerConfig instanceof ProfilerConfig);

    if ('dev' === $containerConfigurator->env()) {
        $webProfilerConfig->toolbar(true);
        $frameworkProfilerConfig->collectSerializerData(true);
    }

    if ('test' === $containerConfigurator->env()) {
        $frameworkProfilerConfig->collect(false);
    }
};
