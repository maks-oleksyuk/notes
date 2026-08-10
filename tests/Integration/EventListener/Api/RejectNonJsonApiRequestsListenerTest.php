<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventListener\Api;

use App\EventListener\Api\RejectNonJsonApiRequestsListener;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

#[CoversClass(RejectNonJsonApiRequestsListener::class)]
final class RejectNonJsonApiRequestsListenerTest extends KernelTestCase
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

    public function testBrowserRequestToApiPathGets406HtmlPage(): void
    {
        $listener = new RejectNonJsonApiRequestsListener($this->twig);

        $request = Request::create('/api/v1/users');
        $request->headers->set('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');

        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        ($listener)($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode(), (string) $response->getContent());
        $this->assertStringContainsString('406', (string) $response->getContent());
        $this->assertStringContainsString('Not Acceptable', (string) $response->getContent());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testJsonClientRequestToApiPathIsLeftAlone(): void
    {
        $listener = new RejectNonJsonApiRequestsListener($this->twig);

        $request = Request::create('/api/v1/users');
        $request->headers->set('Accept', 'application/json');

        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        ($listener)($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testXmlHttpRequestToApiPathIsLeftAlone(): void
    {
        $listener = new RejectNonJsonApiRequestsListener($this->twig);

        $request = Request::create('/api/v1/users');
        $request->headers->set('Accept', 'text/html');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        ($listener)($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testBrowserRequestToNonApiPathIsLeftAlone(): void
    {
        $listener = new RejectNonJsonApiRequestsListener($this->twig);

        $request = Request::create('/login');
        $request->headers->set('Accept', 'text/html');

        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        ($listener)($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testSubRequestToApiPathIsLeftAlone(): void
    {
        $listener = new RejectNonJsonApiRequestsListener($this->twig);

        $request = Request::create('/api/v1/users');
        $request->headers->set('Accept', 'text/html');

        $event = new RequestEvent($this->httpKernel, $request, HttpKernelInterface::SUB_REQUEST);

        ($listener)($event);

        $this->assertFalse($event->hasResponse());
    }
}
