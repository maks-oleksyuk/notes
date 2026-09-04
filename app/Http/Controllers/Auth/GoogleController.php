<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateWithGoogleAction;
use App\Exceptions\GoogleSignInException;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final readonly class GoogleController
{
    public function __construct(
        private Redirector $redirector,
        private AuthenticateWithGoogleAction $authenticateWithGoogle,
    ) {}

    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            ($this->authenticateWithGoogle)();
        } catch (GoogleSignInException $googleSignInException) {
            Notification::make()
                ->title($googleSignInException->getMessage())
                ->danger()
                ->send();

            return $this->redirector->route('filament.admin.auth.login');
        }

        return $this->redirector->intended(
            filament()->getDefaultPanel()->getUrl(),
        );
    }
}
