<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\Auth\LoginRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Request;

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
            ->and(new Collection($rules['password']))
            ->contains(fn ($rule): bool => $rule instanceof Password)
            ->toBeTrue();
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

    it('generates correct throttle key format', function (array $data, ?string $ip, string $expected): void {
        $server = $ip ? ['REMOTE_ADDR' => $ip] : [];

        $request = LoginRequest::create(
            uri: '/api/v1/auth/login',
            method: Request::METHOD_POST,
            parameters: $data,
            server: $server,
        );

        expect($request->throttleKey())->toBe($expected);
    })->with([
        'uppercase email converted to lowercase' => [
            ['email' => 'Test@Example.COM', 'password' => 'password123'],
            null,
            'test@example.com|127.0.0.1',
        ],
        'already lowercase email' => [
            ['email' => 'test@example.com', 'password' => 'password123'],
            null,
            'test@example.com|127.0.0.1',
        ],
        'empty email' => [
            ['password' => 'password123'],
            null,
            '|127.0.0.1',
        ],
        'custom IP address' => [
            ['email' => 'user@example.com', 'password' => 'password123'],
            '192.168.1.100',
            'user@example.com|192.168.1.100',
        ],
        'mixed case email with custom IP' => [
            ['email' => 'User@DOMAIN.com', 'password' => 'password123'],
            '10.0.0.1',
            'user@domain.com|10.0.0.1',
        ],
    ]);

    it('generates unique throttle keys for different combinations', function (): void {
        $key1 = LoginRequest::create(
            uri: '/api/v1/auth/login',
            method: Request::METHOD_POST,
            parameters: ['email' => 'user@example.com', 'password' => 'password123'],
            server: ['REMOTE_ADDR' => '192.168.1.1']
        )->throttleKey();

        $key2 = LoginRequest::create(
            uri: '/api/v1/auth/login',
            method: Request::METHOD_POST,
            parameters: ['email' => 'user@example.com', 'password' => 'password123'],
            server: ['REMOTE_ADDR' => '192.168.1.2']
        )->throttleKey();

        $key3 = LoginRequest::create(
            uri: '/api/v1/auth/login',
            method: 'POST',
            parameters: ['email' => 'another@example.com', 'password' => 'password123'],
            server: ['REMOTE_ADDR' => '192.168.1.1']
        )->throttleKey();

        expect($key1)->toBe('user@example.com|192.168.1.1')
            ->and($key2)->toBe('user@example.com|192.168.1.2')
            ->and($key3)->toBe('another@example.com|192.168.1.1')
            ->and($key1)->not->toBe($key2)
            ->and($key1)->not->toBe($key3)
            ->and($key2)->not->toBe($key3);
    });
});
