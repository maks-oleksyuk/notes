<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventListener\Api;

use App\EventListener\Api\ApiExceptionListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ApiExceptionListener::class)]
final class ApiExceptionListenerTest extends KernelTestCase
{
    private HttpKernelInterface $httpKernel;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->httpKernel = self::getContainer()->get(HttpKernelInterface::class);
    }

    public function testNonApiPathDoesNotSetResponse(): void
    {
        $listener = new ApiExceptionListener(new NullLogger());

        $request = Request::create('/home');
        $exception = new \RuntimeException('Should not be handled');
        $event = new ExceptionEvent(
            $this->httpKernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        ($listener)($event);

        $this->assertNotInstanceOf(Response::class, $event->getResponse());
    }

    /**
     * @return iterable<string, array{0: \Throwable, 1: int, 2: string, 3: string}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'generic exception falls back to 500 and hides the real message' => [
            new \RuntimeException('Something went wrong'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            LogLevel::ERROR,
            'Internal Server Error',
        ];

        yield 'HttpException keeps its own status and message' => [
            new HttpException(Response::HTTP_NOT_FOUND, 'Not Found Error'),
            Response::HTTP_NOT_FOUND,
            LogLevel::WARNING,
            'Not Found Error',
        ];
    }

    #[DataProvider('exceptionProvider')]
    public function testExceptionProducesJsonProblemAndLogsIt(
        \Throwable $exception,
        int $expectedStatus,
        string $expectedLogLevel,
        string $expectedMessage,
    ): void {
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ApiExceptionListener($logger);

        $request = Request::create('/api/v1/test', Request::METHOD_POST);
        $event = new ExceptionEvent(
            $this->httpKernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $logger
            ->expects($this->once())
            ->method('log')
            ->with(
                $expectedLogLevel,
                $expectedMessage,
                self::callback(static fn (array $context): bool => $expectedStatus === $context['status_code']
                        && $exception::class === $context['exception_class']
                        && Request::METHOD_POST === $context['method']
                        && '/api/v1/test' === $context['uri'])
            );

        ($listener)($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expectedStatus, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame([
            'title' => $expectedMessage,
            'status' => $expectedStatus,
            'instance' => '/api/v1/test',
        ], $data);
    }

    public function testJwtFailureProduces401JsonProblemWithBearerChallengeAndLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ApiExceptionListener($logger);

        $request = Request::create('/api/v1/users');
        $exception = new InvalidTokenException('Invalid JWT Token');
        $event = new JWTInvalidEvent($exception, new JWTAuthenticationFailureResponse($exception->getMessage()), $request);

        $logger
            ->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::WARNING,
                'Invalid JWT Token',
                self::callback(static fn (array $context): bool => Response::HTTP_UNAUTHORIZED === $context['status_code']
                        && InvalidTokenException::class === $context['exception_class']
                        && Request::METHOD_GET === $context['method']
                        && '/api/v1/users' === $context['uri'])
            );

        ($listener)($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertSame('Bearer', $response->headers->get('WWW-Authenticate'));

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame([
            'title' => 'Invalid JWT Token',
            'status' => Response::HTTP_UNAUTHORIZED,
            'instance' => '/api/v1/users',
        ], $data);
    }

    public function testJwtFailureOnNonApiPathLeavesOriginalResponseUntouched(): void
    {
        $listener = new ApiExceptionListener(new NullLogger());

        $request = Request::create('/login');
        $exception = new InvalidTokenException('Invalid JWT Token');
        $originalResponse = new JWTAuthenticationFailureResponse($exception->getMessage());
        $event = new JWTInvalidEvent($exception, $originalResponse, $request);

        ($listener)($event);

        $this->assertSame($originalResponse, $event->getResponse());
    }
}
