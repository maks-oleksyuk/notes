<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final readonly class GoogleController
{
    public function __construct(
        private AuthManager $authManager,
        private Redirector $redirector,
    ) {}

    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return $this->redirector->route('filament.admin.auth.login')
                ->withErrors(['email' => __('Google sign-in failed, please try again.')]);
        }

        $rawGoogleUser = $googleUser instanceof AbstractUser ? $googleUser->getRaw() : [];

        if (($rawGoogleUser['email_verified'] ?? false) !== true) {
            return $this->redirector->route('filament.admin.auth.login')
                ->withErrors(['email' => __('Your Google email address is not verified.')]);
        }

        $user = User::query()->updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
            ],
        );

        $this->authManager->login($user, remember: true);

        return $this->redirector->intended(
            filament()->getDefaultPanel()->getUrl(),
        );
    }
}
