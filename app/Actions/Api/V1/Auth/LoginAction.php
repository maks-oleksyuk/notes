<?php

declare(strict_types=1);

namespace App\Actions\Api\V1\Auth;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

final readonly class LoginAction
{
    public function __construct(
        private Hasher $hasher,
    ) {}

    /**
     * @return string[]
     *
     * @throws \Throwable
     */
    public function __invoke(string $email, #[\SensitiveParameter] string $password): array
    {
        $user = User::query()->whereEmail($email)->first();

        throw_if(! $user || ! $this->hasher->check($password, $user->password), ValidationException::withMessages([
            'password' => [__('auth.failed')],
        ]));

        $expiresAt = Date::now()->addHour();
        $token = $user->createToken(name: 'api', expiresAt: $expiresAt)->plainTextToken;

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
