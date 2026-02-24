<?php

declare(strict_types=1);

return [
    'doctrine' => [
        'dbal' => [
            'connections' => [
                'default' => [
                    'dbname_suffix' => '_test%env(default::TEST_TOKEN)%',
                ],
            ],
        ],
    ],
];
