<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use Pest\Expectation;

covers(UpdateUserRequest::class);

describe('API | V1 | User | UpdateUserRequest', function (): void {
    it('validates authorize',
        fn (): Expectation => expect(new UpdateUserRequest()->authorize())->toBeTrue()
    );

    it('validates rules', function (): void {
        $rules = new UpdateUserRequest()->rules();

        expect($rules)->toHaveKey('name')
            ->and($rules['name'])->toContain('sometimes')
            ->and($rules['name'])->toContain('string')
            ->and($rules['name'])->toContain('max:255')
            ->and($rules)->toHaveKey('email')
            ->and($rules['email'])->toContain('sometimes')
            ->and($rules['email'])->toContain('string')
            ->and($rules['email'])->toContain('email')
            ->and($rules['email'])->toContain('max:255')
            ->and($rules)->toHaveKey('password')
            ->and($rules['password'])->toContain('sometimes')
            ->and($rules['password'])->toContain('string')
            ->and($rules['password'])->toContain('min:8');
    });
});
