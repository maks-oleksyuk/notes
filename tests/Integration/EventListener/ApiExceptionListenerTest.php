<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventListener;

use App\EventListener\ApiExceptionListener;
use PHPUnit\Framework\Attributes\CoversClass;
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

    public function testGenericExceptionProduces500JsonProblemAndLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ApiExceptionListener($logger);

        $request = Request::create('/api/v1/test', Request::METHOD_POST);
        $exception = new \RuntimeException('Something went wrong');
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
                LogLevel::ERROR,
                'Internal Server Error',
                self::callback(static fn (array $context): bool => isset($context['status_code'], $context['exception_class'], $context['method'], $context['uri'])
                        && Response::HTTP_INTERNAL_SERVER_ERROR === $context['status_code']
                        && \RuntimeException::class === $context['exception_class']
                        && Request::METHOD_POST === $context['method']
                        && '/api/v1/test' === $context['uri'])
            );

        ($listener)($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame([
            'title' => 'Internal Server Error',
            'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'instance' => '/api/v1/test',
        ], $data);
    }

    public function testHttpExceptionProducesCustomStatusJsonProblemAndLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ApiExceptionListener($logger);

        $request = Request::create('/api/v1/example');
        $exception = new HttpException(Response::HTTP_NOT_FOUND, 'Not Found Error');
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
                LogLevel::WARNING,
                'Not Found Error',
                self::callback(static fn (array $context): bool => Response::HTTP_NOT_FOUND === $context['status_code']
                        && HttpException::class === $context['exception_class']
                        && Request::METHOD_GET === $context['method']
                        && '/api/v1/example' === $context['uri'])
            );

        ($listener)($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame([
            'title' => 'Not Found Error',
            'status' => Response::HTTP_NOT_FOUND,
            'instance' => '/api/v1/example',
        ], $data);
    }
}
