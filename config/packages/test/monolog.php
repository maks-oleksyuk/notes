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
                    ['code' => Response::HTTP_FORBIDDEN],
                    ['code' => Response::HTTP_NOT_FOUND],
                    ['code' => Response::HTTP_METHOD_NOT_ALLOWED],
                ],
                'channels' => ['!event'],
            ],

            'nested' => [
                'type' => 'stream',
                'level' => LogLevel::DEBUG,
                'filename_format' => '{date}',
                'path' => \sprintf('%s/%s/.log', param('kernel.logs_dir'), param('kernel.environment')),
            ],
        ],
    ],
]);
