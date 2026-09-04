<?php

declare(strict_types=1);

use App\Http\Resources\Api\V1\Auth\TokenResource;
use Illuminate\Http\Request;

covers(TokenResource::class);

describe('API | V1 | Resource | Auth', function (): void {

    it('returns expected array with all fields', function (): void {
        $resource = new TokenResource([
            'token' => 'abc123',
            'expires_at' => '2025-12-31T23:59:59Z',
        ]);

        expect($resource->toArray(new Request))->toBe([
            'token' => 'abc123',
            'token_type' => 'Bearer',
            'expires_at' => '2025-12-31T23:59:59Z',
        ]);
    });

});
