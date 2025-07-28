<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX - 1000)]
final readonly class MaintenanceKernelRequestSubscriber
{
    public function __construct(
        private bool $isMaintenance,
        private Environment $twig,
        private ?Profiler $profiler,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$this->isMaintenance || !$event->isMainRequest()) {
            return;
        }

        $this->profiler?->disable();

        $event->setResponse(new Response(
            $this->twig->render('system/maintenance.html.twig'),
            Response::HTTP_SERVICE_UNAVAILABLE
        ));

        $event->stopPropagation();
    }
}
