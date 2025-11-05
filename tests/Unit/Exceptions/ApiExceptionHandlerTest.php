<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

covers(ApiExceptionHandler::class);

beforeEach(fn (): array => [
    $this->responseFactory = App::make(ResponseFactory::class),
    $this->handler = new ApiExceptionHandler($this->responseFactory),
]);

describe('API | ExceptionHandler', function (): void {
    it('returns null if request does not expect json', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(false);
        $request->shouldReceive('is')->andReturn(false);

        $exception = new Exception('Error', Response::HTTP_INTERNAL_SERVER_ERROR);

        $result = ($this->handler)($exception, $request);
        expect($result)->toBeNull();
    });

    it('returns null if request is not api', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->with('api/v*')->andReturn(false);

        $exception = new Exception('Error', Response::HTTP_INTERNAL_SERVER_ERROR);

        $result = ($this->handler)($exception, $request);
        expect($result)->toBeNull();
    });

    it('handles HttpExceptionInterface properly', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/test');

        $exception = new HttpException(Response::HTTP_FORBIDDEN, 'Forbidden message');

        $response = ($this->handler)($exception, $request);

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
            ->and($response->headers->get('Content-Type'))->toBe('application/problem+json');

        $data = json_decode((string) $response->getContent(), true);
        expect($data['title'])->toBe(Response::$statusTexts[Response::HTTP_FORBIDDEN])
            ->and($data['status'])->toBe(Response::HTTP_FORBIDDEN)
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

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);

        $data = json_decode((string) $response->getContent(), true);
        expect($data['title'])->toBe(Response::$statusTexts[Response::HTTP_UNAUTHORIZED])
            ->and($data['status'])->toBe(Response::HTTP_UNAUTHORIZED)
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
        $validationException->shouldReceive('getCode')->andReturn(Response::HTTP_UNPROCESSABLE_ENTITY);
        $validationException->shouldReceive('getMessage')->andReturn('Validation failed');
        $validationException->shouldReceive('errors')->andReturn(['field' => ['error message']]);

        $response = ($this->handler)($validationException, $request);

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = json_decode((string) $response->getContent(), true);
        expect($data['title'])->toBe(Response::$statusTexts[Response::HTTP_UNPROCESSABLE_ENTITY])
            ->and($data['status'])->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->and($data['instance'])->toBe('/api/v1/validate')
            ->and($data['errors'])->toEqual(['field' => ['error message']]);
    });

    it('handles default exception properly', function (): void {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('is')->andReturn(true);
        $request->shouldReceive('getRequestUri')->andReturn('/api/v1/default');

        $exception = new Exception('Default error', Response::HTTP_I_AM_A_TEAPOT);

        $response = ($this->handler)($exception, $request);
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(Response::HTTP_I_AM_A_TEAPOT);

        $data = json_decode((string) $response->getContent(), true);
        expect($data['title'])->toBe(Response::$statusTexts[Response::HTTP_I_AM_A_TEAPOT])
            ->and($data['status'])->toBe(Response::HTTP_I_AM_A_TEAPOT)
            ->and($data['detail'])->toBe('Default error')
            ->and($data['instance'])->toBe('/api/v1/default')
            ->and($data)->not->toHaveKey('errors');
    });
});
