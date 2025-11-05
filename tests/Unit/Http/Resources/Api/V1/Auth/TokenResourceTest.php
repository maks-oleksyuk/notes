<?php

declare(strict_types=1);

use App\Http\Resources\Api\V1\Auth\TokenResource;
use Illuminate\Http\Request;
use Pest\Expectation;

covers(TokenResource::class);

beforeEach(fn (): Request => $this->request = new Request);

it('returns expected array with all fields', function (): void {
    $resource = new TokenResource([
        'token' => 'abc123',
        'expires_at' => '2025-12-31T23:59:59Z',
    ]);

    expect($resource->toArray($this->request))->toBe([
        'token' => 'abc123',
        'token_type' => 'Bearer',
        'expires_at' => '2025-12-31T23:59:59Z',
    ]);
});

it('returns empty strings for missing fields',
    fn (): Expectation => expect(new TokenResource([])->toArray($this->request))
        ->toBe([
            'token' => '',
            'token_type' => 'Bearer',
            'expires_at' => '',
        ])
);
