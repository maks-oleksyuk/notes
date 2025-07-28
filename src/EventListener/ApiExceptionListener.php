<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class ApiExceptionListener
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.api')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/v')) {
            return;
        }

        $exception = $event->getThrowable();

        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $message = $exception instanceof HttpExceptionInterface
            ? $exception->getMessage()
            : 'Internal Server Error';

        $this->logger->log(
            $statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR
                ? LogLevel::ERROR
                : (
                    $statusCode >= Response::HTTP_BAD_REQUEST
                    ? LogLevel::WARNING
                    : LogLevel::INFO
                ),
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

        $response = new JsonResponse(
            data: [
                'title' => $message,
                'status' => $statusCode,
                'instance' => $request->getRequestUri(),
            ],
            status: $statusCode,
            headers: [
                'Content-Type' => 'application/problem+json',
            ]
        );

        $event->setResponse($response);
    }
}
