<?php

declare(strict_types=1);

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

return [
    'doctrine' => [
        'dbal' => [
            'connections' => [
                'default' => [
                    'url' => '%env(resolve:DATABASE_URL)%',
                    'profiling_collect_backtrace' => '%kernel.debug%',
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
                            'dir' => '%kernel.project_dir%/src/Entity',
                            'prefix' => 'App\Entity',
                            'alias' => 'App',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
