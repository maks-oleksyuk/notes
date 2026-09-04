<?php

declare(strict_types=1);

return [
    'versions' => [
        'v1' => [
            'routes' => base_path('routes/api/v1.php'),
            'middleware' => ['api', 'auth:sanctum'],
            'name' => 'api.v1.',
        ],
    ],

    'headers' => [
        'enabled' => true,
        'include' => [
            'version' => false,          // X-API-Version
            'status' => true,            // X-API-Version-Status
            'deprecation' => true,       // Deprecation (RFC 8594)
            'sunset' => true,            // Sunset (RFC 7231)
            'successor_link' => true,    // Link rel="successor-version"
        ],
    ],
];
