<?php

declare(strict_types=1);

namespace App\Tests\Application;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end coverage for the custom error page and the API's content-negotiation
 * behavior (browser navigation to /api/v* must never see raw JSON/JWT errors).
 */
#[CoversNothing]
final class ErrorPagesTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient(['debug' => false]);
    }

    public function testUnknownRouteRendersCustom404Page(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/this-route-does-not-exist',
            server: ['HTTP_ACCEPT' => 'text/html'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSelectorTextSame('.code', '404');
        self::assertSelectorTextSame('.message', 'Not Found');
    }

    public function testBrowserRequestToApiEndpointGets406HtmlPageInsteadOfJson(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/users',
            server: ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_ACCEPTABLE);
        self::assertSelectorTextSame('.code', '406');
        self::assertSelectorTextSame('.message', 'Not Acceptable');
    }

    public function testJsonClientWithoutTokenGetsProblemJsonNotHtml(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/users',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('JWT Token not found', $data['title']);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $data['status']);
        $this->assertSame('/api/v1/users', $data['instance']);
    }

    public function testJsonClientWithInvalidTokenGetsProblemJson(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/users',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer not-a-real-token',
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid JWT Token', $data['title']);
    }
}
