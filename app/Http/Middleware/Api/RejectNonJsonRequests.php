<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final readonly class RejectNonJsonRequests
{
    public function __construct(
        private ResponseFactory $responseFactory,
    ) {}

    /**
     * @param  \Closure(Request): (HttpFoundationResponse)  $next
     */
    public function handle(Request $request, \Closure $next): HttpFoundationResponse
    {
        if (! $request->expectsJson() && $request->acceptsHtml()) {
            return $this->responseFactory->view(view: 'errors.406', status: HttpFoundationResponse::HTTP_NOT_ACCEPTABLE);
        }

        return $next($request);
    }
}
