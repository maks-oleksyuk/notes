<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(User::class);
uses(RefreshDatabase::class);

describe('User Model', function (): void {
    it('can create a user', function (): void {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
        ]);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->name)->toBe('John Doe')
            ->and($user->email)->toBe('johndoe@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
        ]);
    });

    it('has correct fillable attributes', function (): void {
        $user = User::factory()->make();

        expect($user->getFillable())->toBe([
            'name',
            'email',
            'password',
        ]);
    });

    it('has correct casts attributes', function (): void {
        $user = User::factory()->make();

        expect($user->getCasts())->toBe([
            'id' => 'int',
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ]);
    });

    it('hashes the password', function (): void {
        $user = User::factory()->create(['password' => 'plain-text-password']);

        expect($user->password)->not->toBe('plain-text-password');
    });

    it('hides sensitive attributes', function (): void {
        $user = User::factory()->make();
        $array = $user->toArray();

        expect($array)->not->toHaveKeys(['password', 'remember_token']);
    });

    it('casts email_verified_at as datetime', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        expect($user->email_verified_at)->toBeInstanceOf(DateTime::class);
    });

    it('grants Filament access', function (): void {
        $user = User::factory()->create();
        $panel = Mockery::mock(Panel::class);

        expect($user->canAccessPanel($panel))->toBeTrue();
    });
});
