<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

covers(AuthController::class);

describe('API | V1 | Actions | Auth', function (): void {
    it('login a user and returns a token', function (): void {
        $password = '!Password123';
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_at',
                ],
            ])
            ->assertJsonPath('data.token_type', 'Bearer');
    });

    it('returns the same error for an unknown email as for a wrong password', function (): void {
        User::factory()->create([
            'email' => 'known@example.com',
            'password' => Hash::make('!Password123'),
        ]);

        $unknownEmail = $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => '!Password123',
        ]);

        $wrongPassword = $this->postJson('/api/v1/login', [
            'email' => 'known@example.com',
            'password' => '!WrongPassword123',
        ]);

        $unknownEmail->assertUnprocessable();
        $wrongPassword->assertUnprocessable();

        expect($unknownEmail->json())->toBe($wrongPassword->json());
    });

    it('throttles login after 5 attempts for the same email and ip', function (): void {
        $user = User::factory()->create([
            'email' => 'throttle@example.com',
            'password' => Hash::make('!Password123'),
        ]);

        $payload = ['email' => $user->email, 'password' => 'wrong-password'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/login', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/v1/login', $payload)->assertTooManyRequests();
    });
});
