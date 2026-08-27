<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

final readonly class RejectNonJsonRequests
{
    /**
     * @param  \Closure(Request): (HttpFoundationResponse)  $next
     */
    public function handle(Request $request, \Closure $next): HttpFoundationResponse
    {
        throw_if(
            ! $request->expectsJson() && $request->acceptsHtml(),
            NotAcceptableHttpException::class,
            'This endpoint only serves JSON responses.'
        );

        return $next($request);
    }
}
