<?php

declare(strict_types=1);

use App\Support\Scribe\Extracting\Strategies\Responses\AppDefaultResponses;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\Parameter;
use Knuckles\Camel\Extraction\Response as ScribeResponse;
use Knuckles\Camel\Extraction\ResponseCollection as ScribeResponseCollection;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

covers(AppDefaultResponses::class);

beforeEach(function (): void {
    $this->scribeStrategy = new AppDefaultResponses(new DocumentationConfig);
    $this->route = new Route(
        [Request::METHOD_GET],
        '/test-uri',
        ['uses' => fn (): null => null]
    );
});

describe('Scribe | AppDefaultResponses', function (): void {
    it('assigns descriptions for standard successful responses', function (): void {
        $endpointData = ExtractedEndpointData::fromRoute($this->route);
        $statuses = [HttpFoundationResponse::HTTP_OK, HttpFoundationResponse::HTTP_CREATED, HttpFoundationResponse::HTTP_NO_CONTENT];

        $endpointData->responses = new ScribeResponseCollection(array_map(
            fn (int $status): ScribeResponse => new ScribeResponse(['status' => $status]),
            $statuses,
        ));

        $this->scribeStrategy->__invoke($endpointData);

        foreach ($statuses as $status) {
            $response = $endpointData->responses->firstWhere('status', $status);
            assert($response instanceof ScribeResponse);

            expect($response->description)->toBe(HttpFoundationResponse::$statusTexts[$status]);
        }
    });

    it('adds unauthorized error response for authenticated endpoints', function (): void {
        $endpointData = ExtractedEndpointData::fromRoute($this->route);
        $endpointData->metadata->authenticated = true;

        $responses = $this->scribeStrategy->__invoke($endpointData);

        assertErrorResponseStructure($responses, HttpFoundationResponse::HTTP_UNAUTHORIZED);
    });

    it('adds not found error response when url parameters exist', function (): void {
        $endpointData = ExtractedEndpointData::fromRoute($this->route);
        $endpointData->urlParameters = [
            'id' => new Parameter(['name' => 'id']),
        ];

        $responses = $this->scribeStrategy->__invoke($endpointData);
        $response = new Collection($responses)->firstWhere('status', HttpFoundationResponse::HTTP_NOT_FOUND);
        assert($response !== null);

        assertErrorResponseStructure($responses, HttpFoundationResponse::HTTP_NOT_FOUND);
        expect($response['content'] ?? '')
            ->not->toContain('\/')
            ->not->toContain('\u')
            ->toContain('/test-uri')
            ->toContain('Not Found');
    });

    it('adds validation error response with parameter descriptions', function (): void {
        $endpointData = ExtractedEndpointData::fromRoute($this->route);
        $endpointData->queryParameters = [
            'query_parameter' => new Parameter([
                'name' => 'query_parameter',
                'description' => 'query parameter / description 📑',
            ]),
        ];
        $endpointData->bodyParameters = [
            'body_parameter' => new Parameter([
                'name' => 'body_parameter',
                'description' => 'body parameter / description 📑',
            ]),
        ];

        $responses = $this->scribeStrategy->__invoke($endpointData);

        $found = new Collection($responses)->firstWhere('status', HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY);
        assert($found !== null);

        expect($found['description'])->toBe(HttpFoundationResponse::$statusTexts[HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY])
            ->and(json_decode((string) ($found['content'] ?? ''), true))->toBe([
                'title' => HttpFoundationResponse::$statusTexts[HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY],
                'status' => HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'string',
                'instance' => '/test-uri',
                'errors' => [
                    'query_parameter' => ['query parameter / description 📑'],
                    'body_parameter' => ['body parameter / description 📑'],
                ],
            ])
            ->and($found['content'] ?? '')->toContain('parameter / description 📑')
            ->and($found['headers'])->toBe(['Content-Type' => 'application/problem+json']);
    });

    it('adds validation error when only body OR only query parameters exist', function (): void {
        $endpointData = ExtractedEndpointData::fromRoute($this->route);
        $endpointData->bodyParameters = [
            'body_only' => new Parameter(['name' => 'body_only', 'description' => 'body only']),
        ];

        $responsesBodyOnly = $this->scribeStrategy->__invoke($endpointData);
        $foundBody = new Collection($responsesBodyOnly)->firstWhere('status', HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY);
        expect($foundBody)->not()->toBeNull();

        $endpointData = ExtractedEndpointData::fromRoute($this->route);
        $endpointData->queryParameters = [
            'query_only' => new Parameter(['name' => 'query_only', 'description' => 'query only']),
        ];

        $responsesQueryOnly = $this->scribeStrategy->__invoke($endpointData);
        $foundQuery = new Collection($responsesQueryOnly)->firstWhere('status', HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY);
        expect($foundQuery)->not()->toBeNull();
    });

    it('adds standard error responses for throttling and downtime', function (): void {
        $endpointData = ExtractedEndpointData::fromRoute($this->route);
        $responses = $this->scribeStrategy->__invoke($endpointData);

        foreach ([HttpFoundationResponse::HTTP_TOO_MANY_REQUESTS, HttpFoundationResponse::HTTP_SERVICE_UNAVAILABLE] as $status) {
            assertErrorResponseStructure($responses, $status);
        }
    });

    it('casts json_encode false to string when encoding invalid UTF-8 data', function (): void {
        $endpointData = ExtractedEndpointData::fromRoute($this->route);

        $uriProperty = new ReflectionProperty($endpointData, 'uri');
        $uriProperty->setValue($endpointData, "\xB1invalid-uri");

        $endpointData->bodyParameters = [
            'broken' => new Parameter(['name' => 'broken', 'description' => "\xB1invalid"]),
        ];

        $ref = new ReflectionClass($this->scribeStrategy);

        foreach (['makeSimpleErrorResponse', 'makeValidationErrorResponse'] as $methodName) {
            $method = $ref->getMethod($methodName);

            /** @var array{status: int, description: string, content: mixed, headers: array<string, string>} $response */
            $response = $method->invoke($this->scribeStrategy, $endpointData, HttpFoundationResponse::HTTP_BAD_REQUEST);

            expect($response['content'])->toBeString();
        }
    });
});

/**
 * @param  array<int, array{status: int, description: string, content?: string, headers: array<string, string>}>  $responses
 */
function assertErrorResponseStructure(array $responses, int $status): void
{
    $found = new Collection($responses)->firstWhere('status', $status);
    assert($found !== null);

    expect($found['description'])->toBe(HttpFoundationResponse::$statusTexts[$status])
        ->and(json_decode((string) ($found['content'] ?? ''), true))->toBe([
            'title' => HttpFoundationResponse::$statusTexts[$status],
            'status' => $status,
            'detail' => 'string',
            'instance' => '/test-uri',
        ])
        ->and($found['headers'])->toBe(['Content-Type' => 'application/problem+json']);
}
