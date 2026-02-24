<?php

declare(strict_types=1);

return [
    'framework' => [
        'form' => [
            'csrf_protection' => [
                'token_id' => 'submit',
            ],
        ],
        'csrf_protection' => [
            'stateless_token_ids' => ['submit', 'authenticate', 'logout'],
        ],
    ],
];
