<?php

declare(strict_types=1);

use Psr\Log\LogLevel;

return [
    'monolog' => [
        'handlers' => [
            'main' => [
                'type' => 'rotating_file',
                'level' => LogLevel::DEBUG,
                'max_files' => 1,
                'filename_format' => '{date}-{filename}',
                'path' => '%kernel.logs_dir%/%kernel.environment%/main.log',
                'channels' => ['!api', '!event', '!deprecation'],
            ],
            'api' => [
                'type' => 'rotating_file',
                'level' => LogLevel::DEBUG,
                'max_files' => 1,
                'filename_format' => '{date}-{filename}',
                'path' => '%kernel.logs_dir%/%kernel.environment%/api.log',
                'channels' => ['api'],
            ],
            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!api', '!event', '!doctrine', '!console', '!deprecation'],
            ],
            'deprecation' => [
                'type' => 'stream',
                'path' => '%kernel.logs_dir%/%kernel.environment%/deprecation.log',
                'formatter' => 'monolog.formatter.line',
                'channels' => ['deprecation'],
            ],
        ],
    ],
];
