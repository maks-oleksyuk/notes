<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\HttpFoundation\Response;

return App::config([
    'nelmio_api_doc' => [
        'type_info' => true,
        'documentation' => [
            'info' => [
                'title' => 'Symfony Notes | API Documentation',
            ],
            'components' => [
                'securitySchemes' => [
                    'Bearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
            'security' => [['Bearer' => []]],
            'paths' => [
                '/api/v1/login' => [
                    'post' => [
                        'summary' => 'Login',
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
                                'description' => Response::$statusTexts[Response::HTTP_OK],
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
                            Response::HTTP_UNAUTHORIZED => ['description' => Response::$statusTexts[Response::HTTP_UNAUTHORIZED]],
                        ],
                    ],
                ],
            ],
        ],
        'html_config' => [
            'scalar_config' => [
                'defaultHttpClient' => ['targetKey' => 'js', 'clientKey' => 'fetch'],
                'hiddenClients' => [
                    'libcurl',
                    'httpclient',
                    'restsharp',
                    'clj_http',
                    'dart/http',
                    'http',
                    'http1.1',
                    'asynchttp',
                    'nethttp',
                    'okhttp',
                    'unirest',
                    'ofetch',
                    'jquery',
                    'xhr',
                    'julia/http',
                    'undici',
                    'nsurlsession',
                    'cohttp',
                    'curl',
                    'guzzle',
                    'laravel',
                    'webrequest',
                    'restmethod',
                    'python3',
                    'requests',
                    'aiohttp',
                    'httpx_sync',
                    'httpx_async',
                    'httr',
                    'httr2',
                    'native',
                    'reqwest',
                    'wget',
                    'httpie',
                ],
            ],
        ],
        'areas' => [
            'default' => ['name_patterns' => ['^api_v']],
        ],
    ],
]);
