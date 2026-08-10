<?php

declare(strict_types=1);

namespace App\EventListener\Api;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: Events::JWT_INVALID)]
#[AsEventListener(event: Events::JWT_EXPIRED)]
#[AsEventListener(event: Events::JWT_NOT_FOUND)]
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class ApiExceptionListener
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.api')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent|AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request instanceof Request || !str_starts_with($request->getPathInfo(), '/api/v')) {
            return;
        }

        $exception = $event instanceof ExceptionEvent ? $event->getThrowable() : $event->getException();

        $statusCode = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $event instanceof AuthenticationFailureEvent => Response::HTTP_UNAUTHORIZED,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        $message = match (true) {
            $exception instanceof HttpExceptionInterface, $event instanceof AuthenticationFailureEvent => $exception->getMessage(),
            default => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
        };

        $this->logger->log(
            match (true) {
                $statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR => LogLevel::ERROR,
                $statusCode >= Response::HTTP_BAD_REQUEST => LogLevel::WARNING,
                default => LogLevel::INFO,
            },
            $message,
            [
                'exception' => $exception,
                'status_code' => $statusCode,
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
                'client_ip' => $request->getClientIp(),
                'exception_class' => $exception::class,
            ]
        );

        $event->setResponse(new JsonResponse(
            data: [
                'title' => $message,
                'status' => $statusCode,
                'instance' => $request->getRequestUri(),
            ],
            status: $statusCode,
            headers: [
                'Content-Type' => 'application/problem+json',
                ...($event instanceof AuthenticationFailureEvent ? ['WWW-Authenticate' => 'Bearer'] : []),
            ],
        ));
    }
}
