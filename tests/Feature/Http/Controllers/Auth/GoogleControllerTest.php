<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleController;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

covers(GoogleController::class);

function mockSocialiteCallback(string $email, string $name, string $googleId): void
{
    $socialiteUser = Mockery::mock(SocialiteUserContract::class);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);
    $socialiteUser->shouldReceive('getId')->andReturn($googleId);

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
});
