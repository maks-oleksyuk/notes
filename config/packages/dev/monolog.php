<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Log\LogLevel;

return App::config([
    'monolog' => [
        'handlers' => [
            'main' => [
                'type' => 'rotating_file',
                'level' => LogLevel::DEBUG,
                'max_files' => 1,
                'filename_format' => '{date}-{filename}',
                'path' => \sprintf('%s/%s/main.log', param('kernel.logs_dir'), param('kernel.environment')),
                'channels' => ['!api', '!event', '!deprecation'],
            ],

            'api' => [
                'type' => 'rotating_file',
                'level' => LogLevel::DEBUG,
                'max_files' => 1,
                'filename_format' => '{date}-{filename}',
                'path' => \sprintf('%s/%s/api.log', param('kernel.logs_dir'), param('kernel.environment')),
                'channels' => ['api'],
            ],

            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!api', '!event', '!doctrine', '!console', '!deprecation'],
            ],

            'deprecation' => [
                'type' => 'stream',
                'path' => \sprintf('%s/%s/deprecation.log', param('kernel.logs_dir'), param('kernel.environment')),
                'formatter' => 'monolog.formatter.line',
                'channels' => ['deprecation'],
            ],
        ],
    ],
]);
