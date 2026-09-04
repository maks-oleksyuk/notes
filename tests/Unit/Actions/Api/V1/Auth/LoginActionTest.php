<?php

declare(strict_types=1);

use App\Actions\Api\V1\Auth\LoginAction;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

covers(LoginAction::class);

beforeEach(fn (): LoginAction => $this->loginAction = App::make(LoginAction::class));

describe('Actions | API | V1 | Auth', function (): void {
    it('throws ValidationException when user not found', function (): void {
        expect(fn () => $this->loginAction->__invoke('nonexistent@example.com', 'any-password'))->toThrow(ValidationException::class);
    });

    it('throws ValidationException when password is invalid', function (): void {
        $user = User::factory()->create(['password' => bcrypt('correct_password')]);
        expect(fn () => $this->loginAction->__invoke($user->email, 'wrong_password'))->toThrow(ValidationException::class);
    });

    it('throws ValidationException with correct message when credentials are invalid', function (): void {
        try {
            $this->loginAction->__invoke('nonexistent@example.com', 'wrong-password');
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $validationException) {
            $messages = $validationException->errors();

            expect($messages)->toHaveKey('password')
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
            ->and($result['token'])->not()->toBeEmpty()
            ->and($expiresAt->diffInMinutes($now))->toBeLessThanOrEqual(60);
    });
});
