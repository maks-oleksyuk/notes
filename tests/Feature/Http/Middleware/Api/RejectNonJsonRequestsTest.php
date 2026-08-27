<?php

declare(strict_types=1);

use App\Http\Middleware\Api\RejectNonJsonRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

covers(RejectNonJsonRequests::class);

beforeEach(fn () => Route::middleware(RejectNonJsonRequests::class)
    ->get('/test-middleware', fn (): JsonResponse => new JsonResponse(['ok' => true]))
);

describe('Middleware | API', function (): void {
    it('lets JSON-capable requests through', function (?string $accept): void {
        $headers = $accept ? ['Accept' => $accept] : [];

        $this->get('/test-middleware', $headers)
            ->assertOk()
            ->assertJson(['ok' => true]);
    })->with([
        ['application/json'],
        ['text/csv'],
    ]);

    it('rejects browser requests with a rendered 406 page', function (?string $accept): void {
        $headers = $accept ? ['Accept' => $accept] : [];

        $response = $this->get('/test-middleware', $headers);

        $response->assertNotAcceptable();

        expect((string) $response->getContent())->toContain('406');
    })->with([
        ['text/html'],
        [null],
    ]);
});
