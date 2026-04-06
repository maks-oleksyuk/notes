<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return Routes::config([
    'controllers' => [
        'resource' => '../../src/Api/V1/Controller/',
        'type' => 'attribute',
        'prefix' => '/api/v1',
        'name_prefix' => 'api_v1_',
    ],
]);
