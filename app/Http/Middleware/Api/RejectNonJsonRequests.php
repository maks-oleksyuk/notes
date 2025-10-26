<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RejectNonJsonRequests
{
    public function __construct(
        private ResponseFactory $responseFactory,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->expectsJson() && $request->acceptsHtml()) {
            return $this->responseFactory->view(view: 'errors.406', status: Response::HTTP_NOT_ACCEPTABLE);
        }

        return $next($request);
    }
}
