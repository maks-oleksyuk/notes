<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final readonly class ApiExceptionHandler
{
    public function __construct(
        private Repository $config,
        private ResponseFactory $responseFactory,
    ) {}

    public function __invoke(\Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->expectsJson() || ! $request->is('api/v*')) {
            return null;
        }

        $status = match (true) {
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            $e instanceof AuthenticationException => HttpFoundationResponse::HTTP_UNAUTHORIZED,
            $e instanceof ValidationException => HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY,
            default => $this->statusFromCode($e->getCode()),
        };

        $isServerError = $status >= HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR;

        $problem = [
            'title' => HttpFoundationResponse::$statusTexts[$status],
            'status' => $status,
            'detail' => $isServerError && ! $this->config->get('app.debug')
                ? HttpFoundationResponse::$statusTexts[$status]
                : $e->getMessage(),
            'instance' => $request->getRequestUri(),
        ];

        if ($e instanceof ValidationException) {
            $problem['errors'] = $e->errors();
        }

        return $this->responseFactory->json($problem, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }

    private function statusFromCode(mixed $code): int
    {
        if (
            is_int($code)
            && isset(HttpFoundationResponse::$statusTexts[$code])
            && $code >= HttpFoundationResponse::HTTP_BAD_REQUEST
        ) {
            return $code;
        }

        return HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR;
    }
}
