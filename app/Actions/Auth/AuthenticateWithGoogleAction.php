<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\GoogleSignInException;
use App\Models\User;
use Illuminate\Auth\AuthManager;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use RuntimeException;
use Throwable;

final readonly class AuthenticateWithGoogleAction
{
    public function __construct(
        private AuthManager $authManager,
    ) {}

    /**
     * Log a user in through Google. Accounts are never created here — they are
     * provisioned elsewhere (seeder, admin) and Google is only a credential.
     *
     * @throws GoogleSignInException
     */
    public function __invoke(): void
    {
        $googleUser = $this->fetchVerifiedGoogleUser();

        $user = $this->resolveExistingUser($googleUser);

        $this->authManager->login($user, remember: true);
    }

    /**
     * @throws GoogleSignInException
     */
    private function fetchVerifiedGoogleUser(): GoogleUser
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $throwable) {
            throw GoogleSignInException::failed($throwable);
        }

        if (! $googleUser instanceof GoogleUser) {
            throw GoogleSignInException::failed(new RuntimeException('Unexpected Socialite user type.'));
        }

        if (($googleUser->getRaw()['email_verified'] ?? false) !== true) {
            throw GoogleSignInException::unverifiedEmail();
        }

        return $googleUser;
    }

    /**
     * Match on the Google identifier first, so a later email change on the Google side still resolves to the same account.
     * Fall back to email only to link an account on its first Google login; the local email is never overwritten.
     *
     * @throws GoogleSignInException
     */
    private function resolveExistingUser(GoogleUser $googleUser): User
    {
        $user = User::query()->firstWhere('google_id', $googleUser->getId());

        if ($user instanceof User) {
            return $user;
        }

        $user = User::query()->firstWhere('email', $googleUser->getEmail());

        if (! $user instanceof User) {
            throw GoogleSignInException::noAccount();
        }

        if ($user->google_id !== null) {
            throw GoogleSignInException::identityMismatch();
        }

        $user->update(['google_id' => $googleUser->getId()]);

        return $user;
    }
}
