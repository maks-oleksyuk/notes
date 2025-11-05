<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\User\StoreUserRequest;
use Pest\Expectation;

covers(StoreUserRequest::class);

describe('API | V1 | User | StoreUserRequest', function (): void {
    it('validates authorize',
        fn (): Expectation => expect(new StoreUserRequest()->authorize())->toBeTrue()
    );

    it('validates rules', function (): void {
        $rules = new StoreUserRequest()->rules();

        expect($rules)->toHaveKey('name')
            ->and($rules['name'])->toContain('required')
            ->and($rules['name'])->toContain('string')
            ->and($rules['name'])->toContain('max:255')
            ->and($rules)->toHaveKey('email')
            ->and($rules['email'])->toContain('required')
            ->and($rules['email'])->toContain('string')
            ->and($rules['email'])->toContain('email')
            ->and($rules['email'])->toContain('max:255')
            ->and($rules['email'])->toContain('unique:users,email')
            ->and($rules)->toHaveKey('password')
            ->and($rules['password'])->toContain('required')
            ->and($rules['password'])->toContain('string')
            ->and($rules['password'])->toContain('min:8');
    });
});
