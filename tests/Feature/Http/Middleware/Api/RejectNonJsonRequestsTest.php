<?php

declare(strict_types=1);

use App\Http\Middleware\Api\RejectNonJsonRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

covers(RejectNonJsonRequests::class);

beforeEach(fn () => Route::middleware(RejectNonJsonRequests::class)
    ->get('/test-middleware', fn (): JsonResponse => new JsonResponse(['ok' => true]))
);

it('correctly handles requests based on Accept header', function (?string $accept, int $status, ?string $view = null): void {
    $headers = $accept ? ['Accept' => $accept] : [];
    $response = $this->get('/test-middleware', $headers);

    $response->assertStatus($status);

    if ($view) {
        $response->assertViewIs($view);
    } else {
        $response->assertJson(['ok' => true]);
    }
})->with([
    ['application/json', Response::HTTP_OK],
    ['text/html', Response::HTTP_NOT_ACCEPTABLE, 'errors.406'],
    [null, Response::HTTP_NOT_ACCEPTABLE, 'errors.406'],
    ['text/csv', Response::HTTP_OK],
]);
