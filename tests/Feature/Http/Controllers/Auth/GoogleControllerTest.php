<?php

declare(strict_types=1);

use App\Actions\Auth\AuthenticateWithGoogleAction;
use App\Exceptions\GoogleSignInException;
use App\Http\Controllers\Auth\GoogleController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

covers(AuthenticateWithGoogleAction::class, GoogleController::class, GoogleSignInException::class);

function mockSocialiteCallback(string $email, string $name, string $googleId, bool $emailVerified = true): void
{
    $socialiteUser = (new SocialiteUser)
        ->setRaw(['email_verified' => $emailVerified])
        ->map(['id' => $googleId, 'name' => $name, 'email' => $email]);

    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);
}

describe('Http | Controllers | Auth | Google', function (): void {
    it('redirects to Google OAuth', function (): void {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('redirect')
            ->once()
            ->andReturn(new SymfonyRedirectResponse('https://accounts.google.com/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google'))
            ->assertRedirect('https://accounts.google.com/oauth2/auth');
    });

    it('logs in a user already linked by google_id', function (): void {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'google_id' => 'google-1',
        ]);

        mockSocialiteCallback('member@example.com', 'Ignored Name', 'google-1');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(url('/admin'))
            // proves `login(remember: true)` — no recaller cookie is queued otherwise
            ->assertCookie(Auth::guard()->getRecallerName());

        $this->assertAuthenticatedAs($user);
    });

    it('still logs the user in after their Google email changed', function (): void {
        $user = User::factory()->create([
            'name' => 'Stable Name',
            'email' => 'old@example.com',
            'google_id' => 'google-1',
        ]);

        mockSocialiteCallback('new@example.com', 'New Google Name', 'google-1');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(url('/admin'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas(User::class, [
            'id' => $user->id,
            'email' => 'old@example.com',
            'name' => 'Stable Name',
            'google_id' => 'google-1',
        ]);
    });

    it('links google_id on the first Google login of an existing account', function (): void {
        $user = User::factory()->create([
            'name' => 'Stable Name',
            'email' => 'member@example.com',
            'google_id' => null,
        ]);

        mockSocialiteCallback('member@example.com', 'New Google Name', 'google-2');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(url('/admin'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas(User::class, [
            'id' => $user->id,
            'email' => 'member@example.com',
            'name' => 'Stable Name',
            'google_id' => 'google-2',
        ]);
    });

    it('rejects login when no account matches the Google email', function (): void {
        mockSocialiteCallback('ghost@example.com', 'Ghost', 'google-x');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('filament.notifications');

        $this->assertGuest();
        $this->assertDatabaseCount(User::class, 0);
    });

    it('rejects login when the email is linked to a different Google account', function (): void {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'google_id' => 'google-original',
        ]);

        mockSocialiteCallback('member@example.com', 'Impostor', 'google-impostor');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('filament.notifications');

        $this->assertGuest();
        $this->assertDatabaseHas(User::class, [
            'id' => $user->id,
            'google_id' => 'google-original',
        ]);
    });

    it('rejects a callback whose Google payload omits email_verified', function (): void {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'google_id' => null,
        ]);

        $socialiteUser = (new SocialiteUser)
            ->setRaw([])
            ->map(['id' => 'google-x', 'name' => 'No Flag', 'email' => 'member@example.com']);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('filament.notifications');

        $this->assertGuest();
        $this->assertDatabaseHas(User::class, [
            'id' => $user->id,
            'google_id' => null,
        ]);
    });

    it('rejects an unverified Google email without linking the account', function (): void {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'google_id' => null,
        ]);

        mockSocialiteCallback('member@example.com', 'Attacker', 'google-evil', emailVerified: false);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('filament.notifications');

        $this->assertGuest();
        $this->assertDatabaseHas(User::class, [
            'id' => $user->id,
            'google_id' => null,
        ]);
    });

    it('redirects to login with an error when Socialite throws', function (): void {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andThrow(new RuntimeException('denied'));

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('filament.notifications');

        $this->assertGuest();
    });

    it('rejects a Socialite user that is not a Google OAuth user', function (): void {
        $minimalUser = new class implements Laravel\Socialite\Contracts\User
        {
            public function getId()
            {
                return 'google-minimal';
            }

            public function getNickname()
            {
                return null;
            }

            public function getName(): string
            {
                return 'Minimal';
            }

            public function getEmail(): string
            {
                return 'minimal@example.com';
            }

            public function getAvatar()
            {
                return null;
            }
        };

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andReturn($minimalUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('filament.notifications');

        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, ['email' => 'minimal@example.com']);
    });
});
