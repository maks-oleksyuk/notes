<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Config\NelmioApiDocConfig;

return static function (NelmioApiDocConfig $nelmioApiDoc): void {
    $nelmioApiDoc->typeInfo(true);

    $nelmioApiDoc->documentation('info', [
        'title' => 'Symfony Notes | API Documentation',
    ]);

    $nelmioApiDoc->documentation('components', [
        'securitySchemes' => [
            'Bearer' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
        ],
    ]);

    $nelmioApiDoc->documentation('security', [['Bearer' => []]]);

    $nelmioApiDoc->areas('default', ['name_patterns' => ['^api_v']]);

    $nelmioApiDoc->documentation('paths', [
        '/api/v1/login' => [
            mb_strtolower(Request::METHOD_POST) => [
                'summary' => 'JWT login',
                'security' => [],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'username' => ['type' => 'string', 'example' => 'api_user'],
                                    'password' => ['type' => 'string', 'example' => '12345678'],
                                ],
                                'required' => ['username', 'password'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    Response::HTTP_OK => [
                        'description' => 'JWT Token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => [
                                            'type' => 'string',
                                            'example' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9…',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_UNAUTHORIZED => ['description' => 'Invalid credentials'],
                ],
            ],
        ],
    ]);
};
