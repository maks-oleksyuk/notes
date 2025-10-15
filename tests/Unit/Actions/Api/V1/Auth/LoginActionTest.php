<?php

declare(strict_types=1);

use App\Actions\Api\V1\Auth\LoginAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

covers(LoginAction::class);

uses(RefreshDatabase::class);

beforeEach(fn (): LoginAction => $this->loginAction = App::make(LoginAction::class));

describe('API | V1 | Auth', function (): void {
    it('throws ValidationException when user not found', function (): void {
        $this->expectException(ValidationException::class);

        $this->loginAction->__invoke('nonexistent@example.com', 'any-password');
    });

    it('throws ValidationException when password is invalid', function (): void {
        $user = User::factory()->create(['password' => bcrypt('correct_password')]);

        $this->expectException(ValidationException::class);

        $this->loginAction->__invoke($user->email, 'wrong_password');
    });

    it('throws ValidationException with correct message when credentials are invalid', function (): void {
        try {
            $this->loginAction->__invoke('nonexistent@example.com', 'wrong-password');
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $validationException) {
            $messages = $validationException->errors();

            expect(array_key_exists('password', $messages))->toBeTrue()
                ->and($messages['password'])->toContain(__('auth.failed'));
        }
    });

    it('returns a token and expiration one hour from now', function (): void {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        Date::setTestNow($now = Date::now());

        $result = $this->loginAction->__invoke($user->email, $password);
        $expiresAt = Date::parse($result['expires_at']);

        expect($result)->toHaveKeys(['token', 'expires_at'])
            ->and($result['token'])->toBeString()->not()->toBeEmpty()
            ->and($expiresAt->diffInMinutes($now))->toBeLessThanOrEqual(60);
    });
});
