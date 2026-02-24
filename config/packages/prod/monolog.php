<?php

declare(strict_types=1);

use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;

return [
    'monolog' => [
        'handlers' => [
            'main' => [
                'type' => 'fingers_crossed',
                'action_level' => LogLevel::ERROR,
                'handler' => 'nested',
                'excluded_http_codes' => [
                    ['code' => Response::HTTP_NOT_FOUND],
                    ['code' => Response::HTTP_METHOD_NOT_ALLOWED],
                ],
                'buffer_size' => 50,
            ],
            'nested' => [
                'type' => 'stream',
                'level' => LogLevel::DEBUG,
                'path' => 'php://stderr',
                'formatter' => 'monolog.formatter.json',
            ],
            'api' => [
                'type' => 'rotating_file',
                'level' => LogLevel::ERROR,
                'max_files' => 7,
                'filename_format' => '{date}-{filename}',
                'path' => '%kernel.logs_dir%/%kernel.environment%/api.log',
                'channels' => ['api'],
            ],
            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!event', '!doctrine'],
            ],
            'deprecation' => [
                'type' => 'stream',
                'path' => 'php://stderr',
                'formatter' => 'monolog.formatter.json',
                'channels' => ['deprecation'],
            ],
        ],
    ],
];
