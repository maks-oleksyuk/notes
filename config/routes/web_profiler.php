<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return Routes::config([
    'when@dev' => [
        'wdt' => [
            'resource' => '@WebProfilerBundle/Resources/config/routing/wdt.php',
            'prefix' => '/_wdt',
        ],
        'profiler' => [
            'resource' => '@WebProfilerBundle/Resources/config/routing/profiler.php',
            'prefix' => '/_profiler',
        ],
    ],
]);
