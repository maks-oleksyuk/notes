<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\Auth\LoginRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

covers(LoginRequest::class);

describe('API | V1 | User | LoginRequest', function (): void {
    it('validates rules', function (): void {
        $rules = new LoginRequest()->rules();
        $hasPasswordRule = new Collection($rules['password'])->contains(fn ($rule): bool => $rule instanceof Password);

        expect($rules)->toHaveKey('email')
            ->and($rules['email'])->toContain('required')
            ->and($rules['email'])->toContain('string')
            ->and($rules['email'])->toContain('email')
            ->and($rules['email'])->toContain('max:255')
            ->and($rules['email'])->not->toContain('exists:users,email')
            ->and($rules)->toHaveKey('password')
            ->and($rules['password'])->toContain('required')
            ->and($hasPasswordRule)->toBeTrue();
    });

    it('enforces Password::defaults() validation rules', function (string $password, bool $shouldPass): void {
        $rules = new LoginRequest()->rules();

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
        'password without uppercase' => ['password0!', false],
        'password without lowercase' => ['PASSW0RD!', false],
    ]);
});
