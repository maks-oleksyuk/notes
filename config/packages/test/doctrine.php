<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'doctrine' => [
        'dbal' => [
            'connections' => [
                'default' => [
                    'dbname_suffix' => '_test'.env('TEST_TOKEN')->default(''),
                ],
            ],
        ],
    ],
]);
