<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;

return App::config([
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
                'channels' => ['!deprecation'],
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
                'path' => \sprintf('%s/%s/api.log', param('kernel.logs_dir'), param('kernel.environment')),
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
]);
