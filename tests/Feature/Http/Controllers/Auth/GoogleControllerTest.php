<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleController;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

covers(GoogleController::class);

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

    it('creates a new user on callback', function (): void {
        mockSocialiteCallback('new@example.com', 'New User', 'google-123');

        $this->get(route('auth.google.callback'));

        $this->assertDatabaseHas(User::class, [
            'email' => 'new@example.com',
            'name' => 'New User',
            'google_id' => 'google-123',
        ]);
    });

    it('updates an existing user on callback', function (): void {
        User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Old Name',
            'google_id' => null,
        ]);

        mockSocialiteCallback('existing@example.com', 'Updated Name', 'google-456');

        $this->get(route('auth.google.callback'));

        $this->assertDatabaseCount(User::class, 1);

        $this->assertDatabaseHas(User::class, [
            'email' => 'existing@example.com',
            'name' => 'Updated Name',
            'google_id' => 'google-456',
        ]);
    });

    it('authenticates, redirects, and remembers user after callback', function (): void {
        mockSocialiteCallback('test@example.com', 'Test User', 'google-123');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(url('/admin'));

        $user = User::query()->where('email', 'test@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        expect($user->remember_token)->not->toBeNull();
    });

    it('redirects to login with an error when Socialite throws', function (): void {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andThrow(new RuntimeException('denied'));

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('rejects a callback whose raw payload omits the email_verified flag', function (): void {
        $socialiteUser = (new SocialiteUser)
            ->setRaw([])
            ->map(['id' => 'google-x', 'name' => 'No Flag', 'email' => 'noflag@example.com']);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, ['email' => 'noflag@example.com']);
    });

    it('rejects a callback when Socialite returns a user without raw payload access', function (): void {
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
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, ['email' => 'minimal@example.com']);
    });

    it('rejects an unverified Google email and does not link an existing account', function (): void {
        User::factory()->create(['email' => 'victim@example.com', 'google_id' => null]);

        mockSocialiteCallback('victim@example.com', 'Attacker', 'google-evil', emailVerified: false);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas(User::class, [
            'email' => 'victim@example.com',
            'google_id' => null,
        ]);
    });
});
