<?php

declare(strict_types=1);

use App\Support\Scribe\Extracting\Strategies\Responses\AppResponseAttributes;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Tests\Fixtures\Scribe\ResponseAttributesController;

covers(AppResponseAttributes::class);

beforeEach(function (): void {
    $this->scribeStrategy = new AppResponseAttributes(new DocumentationConfig);
    $this->endpointFor = fn (string $action): ExtractedEndpointData => ExtractedEndpointData::fromRoute(
        new Route([Request::METHOD_POST], 'api/v1/test', ['uses' => ResponseAttributesController::class.'@'.$action]),
    );
});

describe('Scribe | AppResponseAttributes', function (): void {
    it('renders an array-backed resource from its @property shape', function (): void {
        /** @var list<array{status: int, description: string, content: string}> $responses */
        $responses = $this->scribeStrategy->__invoke(($this->endpointFor)('arrayResource'));

        /** @var array{data: array{token: mixed, token_type: mixed, expires_at: string}} $content */
        $content = json_decode($responses[0]['content'], true);

        expect($responses)->toHaveCount(1)
            ->and($responses[0]['status'])->toBe(HttpFoundationResponse::HTTP_CREATED)
            ->and($responses[0]['description'])->toBe('Created')
            ->and($content['data'])->toHaveKeys(['token', 'token_type', 'expires_at'])
            ->and($content['data']['token'])->toBeString()->not->toBeEmpty()
            ->and($content['data']['token_type'])->toBe('Bearer')
            ->and(DateTimeImmutable::createFromFormat(DATE_ATOM, $content['data']['expires_at']))->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('keeps the default behaviour for model-backed resources', function (): void {
        /** @var list<array{status: int, description: string, content: string}> $responses */
        $responses = $this->scribeStrategy->__invoke(($this->endpointFor)('modelResource'));

        expect($responses)->toHaveCount(1)
            ->and($responses[0]['status'])->toBe(HttpFoundationResponse::HTTP_OK)
            ->and(json_decode($responses[0]['content'], true))->toHaveKey('data.id');
    });

    it('builds examples for list, nullable, union, keyed and nested shape types', function (): void {
        /** @var list<array{status: int, description: string, content: string}> $responses */
        $responses = $this->scribeStrategy->__invoke(($this->endpointFor)('shapeResource'));

        /** @var array{data: array{tags: list<mixed>, rows: list<array<string, mixed>>, count: mixed, score: mixed, quoted: mixed, 9: mixed, pair: list<mixed>, email: string, items: list<array<string, mixed>>, created_at: string}} $content */
        $content = json_decode($responses[0]['content'], true);
        $data = $content['data'];

        expect($data['tags'])->toHaveCount(1)->each->toBeString()
            ->and($data['rows'])->toHaveCount(1)
            ->and($data['rows'][0])->toHaveKey('id')
            ->and($data['count'])->toBeInt()
            ->and($data['score'])->toBeInt()
            ->and($data['quoted'])->toBeBool()
            ->and($data[9])->toBeString()
            ->and($data['pair'])->toHaveCount(2)
            ->and($data['pair'][0])->toBeInt()
            ->and($data['pair'][1])->toBeBool()
            ->and(filter_var($data['email'], FILTER_VALIDATE_EMAIL))->toBe($data['email'])
            ->and($data['items'])->toHaveCount(1)
            ->and($data['items'][0])->toHaveKey('id')
            ->and(DateTimeImmutable::createFromFormat(DATE_ATOM, $data['created_at']))->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('infers the model from the @property $resource docblock', function (): void {
        /** @var list<array{status: int, description: string, content: string}> $responses */
        $responses = $this->scribeStrategy->__invoke(($this->endpointFor)('inferredModelResource'));

        /** @var array{data: array{id: mixed, email: mixed}} $content */
        $content = json_decode($responses[0]['content'], true);

        expect($responses)->toHaveCount(1)
            ->and($content['data']['id'])->toBeInt()
            ->and(filter_var($content['data']['email'], FILTER_VALIDATE_EMAIL))->toBeString();
    });

    it('resolves a fully qualified model class from the @property $resource docblock', function (): void {
        /** @var list<array{status: int, description: string, content: string}> $responses */
        $responses = $this->scribeStrategy->__invoke(($this->endpointFor)('fullyQualifiedModelResource'));

        /** @var array{data: array{email: mixed}} $content */
        $content = json_decode($responses[0]['content'], true);

        expect(filter_var($content['data']['email'], FILTER_VALIDATE_EMAIL))->toBeString();
    });

    it('falls back to the default behaviour for a docblock without a $resource property', function (): void {
        /** @var list<array{status: int, description: string, content: string}> $responses */
        $responses = $this->scribeStrategy->__invoke(($this->endpointFor)('untypedResource'));

        expect(json_decode($responses[0]['content'], true))->toBe(['data' => ['untyped' => true]]);
    });

    it('falls back to the default behaviour for a resource without a docblock', function (): void {
        /** @var list<array{status: int, description: string, content: string}> $responses */
        $responses = $this->scribeStrategy->__invoke(($this->endpointFor)('plainResource'));

        expect(json_decode($responses[0]['content'], true))->toBe(['data' => ['plain' => true]]);
    });
});
