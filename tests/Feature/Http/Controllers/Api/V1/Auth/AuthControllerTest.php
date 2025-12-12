<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

covers(AuthController::class);

pest()->use(RefreshDatabase::class);

describe('API | V1 | Auth', function (): void {
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
            ->assertJsonStructure(['data' => ['token', 'expires_at']]);
    });
});
