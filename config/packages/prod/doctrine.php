<?php

declare(strict_types=1);

return [
    'doctrine' => [
        'orm' => [
            'entity_managers' => [
                'default' => [
                    'query_cache_driver' => [
                        'type' => 'pool',
                        'pool' => 'doctrine.system_cache_pool',
                    ],
                    'result_cache_driver' => [
                        'type' => 'pool',
                        'pool' => 'doctrine.result_cache_pool',
                    ],
                ],
            ],
        ],
    ],
    'framework' => [
        'cache' => [
            'pools' => [
                'doctrine.system_cache_pool' => [
                    'adapters' => ['cache.system'],
                ],
                'doctrine.result_cache_pool' => [
                    'adapters' => ['cache.app'],
                ],
            ],
        ],
    ],
];
