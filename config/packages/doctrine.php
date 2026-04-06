<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

return App::config([
    'doctrine' => [
        'dbal' => [
            'connections' => [
                'default' => [
                    'url' => env('DATABASE_URL')->resolve(),
                    'profiling_collect_backtrace' => param('kernel.debug'),
                ],
            ],
        ],

        'orm' => [
            'entity_managers' => [
                'default' => [
                    'validate_xml_mapping' => true,
                    'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                    'identity_generation_preferences' => [
                        PostgreSQLPlatform::class => 'identity',
                    ],
                    'auto_mapping' => true,
                    'mappings' => [
                        'App' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => param('kernel.project_dir').'/src/Entity',
                            'prefix' => 'App\Entity',
                            'alias' => 'App',
                        ],
                    ],
                ],
            ],
        ],
    ],
]);
