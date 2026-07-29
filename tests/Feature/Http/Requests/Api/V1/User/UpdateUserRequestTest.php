<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use Pest\Expectation;

covers(UpdateUserRequest::class);

describe('API | V1 | User | UpdateUserRequest', function (): void {
    it('validates authorize',
        fn (): Expectation => expect(new UpdateUserRequest()->authorize())->toBeTrue()
    );

    it('validates rules', function (): void {
        $rules = new UpdateUserRequest()->rules();
        $hasEmailUniqueRule = new Collection($rules['email'])->contains(fn ($rule): bool => $rule instanceof Unique);
        $hasPasswordRule = new Collection($rules['password'])->contains(fn ($rule): bool => $rule instanceof Password);

        expect($rules)->toHaveKey('name')
            ->and($rules['name'])->toContain('sometimes')
            ->and($rules['name'])->toContain('string')
            ->and($rules['name'])->toContain('max:255')
            ->and($rules)->toHaveKey('email')
            ->and($rules['email'])->toContain('sometimes')
            ->and($rules['email'])->toContain('string')
            ->and($rules['email'])->toContain('email')
            ->and($rules['email'])->toContain('max:255')
            ->and($hasEmailUniqueRule)->toBeTrue()
            ->and($rules)->toHaveKey('password')
            ->and($rules['password'])->toContain('sometimes')
            ->and($hasPasswordRule)->toBeTrue();
    });

    it('enforces Password::defaults() validation rules when password is provided', function (string $password, bool $shouldPass): void {
        $rules = new UpdateUserRequest()->rules();

        $validator = Validator::make(
            ['password' => $password],
            ['password' => $rules['password']]
        );

        expect($validator->passes())->toBe($shouldPass);
    })->with([
        'valid password with all requirements' => ['!Password123', true],
        'password too short' => ['Pass1!', false],
        'password without numbers' => ['Password!', false],
        'password without symbols' => ['Password1', false],
        'password without uppercase' => ['password1!', false],
        'password without lowercase' => ['PASSW0RD!', false],
    ]);
});
