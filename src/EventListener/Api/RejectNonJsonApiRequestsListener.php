<?php

declare(strict_types=1);

namespace App\EventListener\Api;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX - 1000)]
final readonly class RejectNonJsonApiRequestsListener
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api/v')) {
            return;
        }

        $acceptableContentTypes = $request->getAcceptableContentTypes();

        $expectsJson = $request->isXmlHttpRequest()
            || \in_array('application/json', $acceptableContentTypes, true);

        $acceptsHtml = \in_array('text/html', $acceptableContentTypes, true)
            || \in_array('*/*', $acceptableContentTypes, true);

        if ($expectsJson || !$acceptsHtml) {
            return;
        }

        $event->setResponse(new Response(
            $this->twig->render('bundles/TwigBundle/Exception/error.html.twig', [
                'status_code' => Response::HTTP_NOT_ACCEPTABLE,
                'status_text' => Response::$statusTexts[Response::HTTP_NOT_ACCEPTABLE],
            ]),
            Response::HTTP_NOT_ACCEPTABLE,
        ));

        $event->stopPropagation();
    }
}
