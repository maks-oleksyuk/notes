<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\Auth\LoginRequest;

covers(LoginRequest::class);

describe('API | V1 | User | LoginRequest', function (): void {
    it('validates rules', function (): void {
        $rules = new LoginRequest()->rules();

        expect($rules)->toHaveKey('email')
            ->and($rules['email'])->toContain('required')
            ->and($rules['email'])->toContain('string')
            ->and($rules['email'])->toContain('email')
            ->and($rules['email'])->toContain('max:255')
            ->and($rules['email'])->toContain('exists:users,email')
            ->and($rules)->toHaveKey('password')
            ->and($rules['password'])->toContain('required')
            ->and($rules['password'])->toContain('string')
            ->and($rules['password'])->toContain('min:8');
    });
});
