<?php

declare(strict_types=1);

return [
    'framework' => [
        'test' => true,
        'session' => [
            'storage_factory_id' => 'session.storage.factory.mock_file',
        ],
    ],
];
