<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

covers(ApiExceptionHandler::class);

beforeEach(function (): void {
    $this->responseFactory = App::make(ResponseFactory::class);
    $this->handler = App::make(ApiExceptionHandler::class);
});

describe('API | ExceptionHandler', function (): void {
    it('returns null if request does not expect json', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(false);
        $request->shouldReceive('is')->andReturn(false);

        $exception = new Exception('Error', HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR);

        $result = ($this->handler)($exception, $request);
        expect($result)->toBeNull();
    });

    it('returns null if request is not api', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->with('api/v*')->andReturn(false);

        $exception = new Exception('Error', HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR);

        $result = ($this->handler)($exception, $request);
        expect($result)->toBeNull();
    });

    it('handles HttpExceptionInterface properly', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/test');

        $exception = new HttpException(HttpFoundationResponse::HTTP_FORBIDDEN, 'Forbidden message');

        $response = ($this->handler)($exception, $request);
        assert($response instanceof JsonResponse);

        expect($response)
            ->and($response->getStatusCode())->toBe(HttpFoundationResponse::HTTP_FORBIDDEN)
            ->and($response->headers->get('Content-Type'))->toBe('application/problem+json');

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        expect($data['title'])->toBe(HttpFoundationResponse::$statusTexts[HttpFoundationResponse::HTTP_FORBIDDEN])
            ->and($data['status'])->toBe(HttpFoundationResponse::HTTP_FORBIDDEN)
            ->and($data['detail'])->toBe('Forbidden message')
            ->and($data['instance'])->toBe('/api/v1/test')
            ->and($data)->not->toHaveKey('errors');
    });

    it('handles AuthenticationException properly', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/auth');

        $exception = new AuthenticationException('Unauthenticated');

        $response = ($this->handler)($exception, $request);
        assert($response instanceof JsonResponse);

        expect($response->getStatusCode())->toBe(HttpFoundationResponse::HTTP_UNAUTHORIZED);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        expect($data['title'])->toBe(HttpFoundationResponse::$statusTexts[HttpFoundationResponse::HTTP_UNAUTHORIZED])
            ->and($data['status'])->toBe(HttpFoundationResponse::HTTP_UNAUTHORIZED)
            ->and($data['detail'])->toBe('Unauthenticated')
            ->and($data['instance'])->toBe('/api/v1/auth')
            ->and($data)->not->toHaveKey('errors');
    });

    it('handles ValidationException properly', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/validate');

        $validationException = Mockery::mock(ValidationException::class);
        $validationException->shouldReceive('getCode')->andReturn(HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY);
        $validationException->shouldReceive('getMessage')->andReturn('Validation failed');
        $validationException->shouldReceive('errors')->andReturn(['field' => ['error message']]);

        $response = ($this->handler)($validationException, $request);
        assert($response instanceof JsonResponse);

        expect($response->getStatusCode())->toBe(HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        expect($data['title'])->toBe(HttpFoundationResponse::$statusTexts[HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY])
            ->and($data['status'])->toBe(HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY)
            ->and($data['instance'])->toBe('/api/v1/validate')
            ->and($data['errors'])->toEqual(['field' => ['error message']]);
    });

    it('falls back to 500 when the exception code is not an HTTP status', function (): void {
        config(['app.debug' => false]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/boom');

        $exception = new RuntimeException('Internal details that must stay hidden');

        $response = ($this->handler)($exception, $request);
        assert($response instanceof JsonResponse);

        expect($response->getStatusCode())->toBe(HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        expect($data['status'])->toBe(HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR)
            ->and($data['detail'])->toBe(HttpFoundationResponse::$statusTexts[HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR])
            ->and($data['detail'])->not->toContain('Internal details');
    });

    it('keeps the exception message for server errors when debug is enabled', function (): void {
        config(['app.debug' => true]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/boom');

        $response = ($this->handler)(new RuntimeException('Debug detail'), $request);
        assert($response instanceof JsonResponse);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        expect($data['detail'])->toBe('Debug detail');
    });

    it('maps exception code 400 to HTTP 400 status', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/test');

        $response = ($this->handler)(new Exception('bad request', HttpFoundationResponse::HTTP_BAD_REQUEST), $request);
        assert($response instanceof JsonResponse);

        expect($response->getStatusCode())->toBe(HttpFoundationResponse::HTTP_BAD_REQUEST);
    });

    it('falls back to 500 when exception code is int but not a known HTTP status', function (): void {
        config(['app.debug' => true]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/test');

        $response = ($this->handler)(new Exception('msg', 499), $request);
        assert($response instanceof JsonResponse);

        expect($response->getStatusCode())->toBe(HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR);
    });

    it('handles default exception properly', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/default');

        $exception = new Exception('Default error', HttpFoundationResponse::HTTP_I_AM_A_TEAPOT);

        $response = ($this->handler)($exception, $request);
        assert($response instanceof JsonResponse);

        expect($response->getStatusCode())->toBe(HttpFoundationResponse::HTTP_I_AM_A_TEAPOT);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        expect($data['title'])->toBe(HttpFoundationResponse::$statusTexts[HttpFoundationResponse::HTTP_I_AM_A_TEAPOT])
            ->and($data['status'])->toBe(HttpFoundationResponse::HTTP_I_AM_A_TEAPOT)
            ->and($data['detail'])->toBe('Default error')
            ->and($data['instance'])->toBe('/api/v1/default')
            ->and($data)->not->toHaveKey('errors');
    });
});
