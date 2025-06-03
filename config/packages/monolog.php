<?php

declare(strict_types=1);

use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Config\Monolog\HandlerConfig\ExcludedHttpCodeConfig;
use Symfony\Config\MonologConfig;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (MonologConfig $monologConfig, ContainerConfigurator $containerConfigurator): void {
    $monologConfig->channels(['deprecation']);

    switch ($containerConfigurator->env()) {
        case 'prod':
            $monologConfig->handler('main')
                ->type('fingers_crossed')
                ->actionLevel(LogLevel::ERROR)
                ->handler('nested')
                ->excludedHttpCode(new ExcludedHttpCodeConfig(['code' => Response::HTTP_NOT_FOUND]))
                ->excludedHttpCode(new ExcludedHttpCodeConfig(['code' => Response::HTTP_METHOD_NOT_ALLOWED]))
                ->bufferSize(50);

            $monologConfig->handler('nested')
                ->type('stream')
                ->level(LogLevel::DEBUG)
                ->path('php://stderr')
                ->formatter('monolog.formatter.json');

            $monologConfig->handler('console')
                ->type('console')
                ->processPsr3Messages(false)
                ->channels()->elements(['!event', '!doctrine']);

            $monologConfig->handler('deprecation')
                ->type('stream')
                ->path('php://stderr')
                ->formatter('monolog.formatter.json')
                ->channels()->elements(['deprecation']);

            break;
        case 'test':
            $monologConfig->handler('main')
                ->type('fingers_crossed')
                ->actionLevel(LogLevel::ERROR)
                ->handler('nested')
                ->excludedHttpCode(new ExcludedHttpCodeConfig(['code' => Response::HTTP_FORBIDDEN]))
                ->excludedHttpCode(new ExcludedHttpCodeConfig(['code' => Response::HTTP_NOT_FOUND]))
                ->excludedHttpCode(new ExcludedHttpCodeConfig(['code' => Response::HTTP_METHOD_NOT_ALLOWED]))
                ->channels()->elements(['!event']);

            $monologConfig->handler('nested')
                ->type('stream')
                ->level(LogLevel::DEBUG)
                ->path(sprintf('%s/%s.log', param('kernel.logs_dir'), param('kernel.environment')));

            break;
        case 'dev':
            $monologConfig->handler('main')
                ->type('rotating_file')
                ->level(LogLevel::DEBUG)
                ->maxFiles(1)
                ->path(sprintf('%s/%s.log', param('kernel.logs_dir'), param('kernel.environment')))
                ->channels()->elements(['!event', '!deprecation']);

            $monologConfig->handler('console')
                ->type('console')
                ->processPsr3Messages(false)
                ->channels()->elements([
                    '!event',
                    '!doctrine',
                    '!console',
                    '!deprecation',
                ]);

            $monologConfig->handler('deprecation')
                ->type('stream')
                ->path(sprintf('%s/%s-deprecation.log', param('kernel.logs_dir'), param('kernel.environment')))
                ->formatter('monolog.formatter.line')
                ->channels()->elements(['deprecation']);

            break;
        default:
            break;
    }
};
