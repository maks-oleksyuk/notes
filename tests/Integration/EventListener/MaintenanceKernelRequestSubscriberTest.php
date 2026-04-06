<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventListener;

use App\EventListener\MaintenanceKernelRequestSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;

#[CoversClass(MaintenanceKernelRequestSubscriber::class)]
final class MaintenanceKernelRequestSubscriberTest extends KernelTestCase
{
    private HttpKernelInterface $httpKernel;

    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->httpKernel = $container->get(HttpKernelInterface::class);
        $this->twig = $container->get(Environment::class);
    }

    public function testDoesNothingWhenMaintenanceModeIsOff(): void
    {
        $listener = new MaintenanceKernelRequestSubscriber(false, $this->twig, null);
        $event = new RequestEvent($this->httpKernel, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $listener($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testSets503ResponseAndStopsPropagation(): void
    {
        $listener = new MaintenanceKernelRequestSubscriber(true, $this->twig, null);
        $event = new RequestEvent($this->httpKernel, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $listener($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testDisablesProfilerIfExists(): void
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())->method('disable');

        $listener = new MaintenanceKernelRequestSubscriber(true, $this->twig, $profiler);
        $event = new RequestEvent($this->httpKernel, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $listener($event);

        $this->assertTrue($event->hasResponse());
    }
}
