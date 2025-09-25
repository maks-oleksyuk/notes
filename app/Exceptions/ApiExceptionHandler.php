<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ApiExceptionHandler
{
    public function __construct(
        private ResponseFactory $responseFactory,
    ) {}

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->expectsJson() || ! $request->is('api/v*')) {
            return null;
        }

        $status = $e instanceof HttpExceptionInterface
            ? $e->getStatusCode()
            : $e->getCode();

        $problem = [
            'title' => Response::$statusTexts[$status],
            'status' => $status,
            'detail' => $e->getMessage(),
            'instance' => $request->getRequestUri(),
        ];

        if ($e instanceof ValidationException) {
            $problem['errors'] = $e->errors();
        }

        return $this->responseFactory->json($problem, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
