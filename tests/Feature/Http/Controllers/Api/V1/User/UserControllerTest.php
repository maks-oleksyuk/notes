<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Requests\PaginatedRequest;
use App\Models\User;

covers(UserController::class);

beforeEach(function (): void {
    $this->baseUrl = '/api/v1/users';
    $this->actingAs(User::factory()->superAdmin()->create());
});

describe('API | V1 | Actions | Users', function (): void {
    it('paginates the user list for an authenticated user', function (): void {
        User::factory()->count(20)->create();

        $this->getJson($this->baseUrl)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email']],
                'links',
                'meta',
            ])
            ->assertJsonCount(PaginatedRequest::DEFAULT_PER_PAGE, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.current_page', 1);

        $this->getJson($this->baseUrl.'?page=2')
            ->assertOk()
            ->assertJsonCount(21 - PaginatedRequest::DEFAULT_PER_PAGE, 'data')
            ->assertJsonPath('meta.current_page', 2);
    });

    it('honours a valid per_page value', function (): void {
        User::factory()->count(5)->create();

        $this->getJson($this->baseUrl.'?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3);
    });

    it('creates a new user when authenticated', function (): void {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => '!Password123',
        ];

        $response = $this->postJson($this->baseUrl, $payload);

        $response
            ->assertCreated()
            ->assertJson(['data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]]);

        $this->assertDatabaseHas(User::class, ['email' => 'john@example.com']);
    });

    it('shows a specific user when authenticated', function (): void {
        $user = User::factory()->create();

        $response = $this->getJson(sprintf('%s/%s', $this->baseUrl, $user->id));

        $response
            ->assertOk()
            ->assertJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]]);
    });

    it('updates an existing user when authenticated', function (): void {
        $user = User::factory()->create();

        $payload = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->putJson(sprintf('%s/%s', $this->baseUrl, $user->id), $payload);

        $response
            ->assertOk()
            ->assertJson(['data' => [
                'id' => $user->id,
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]]);

        $user->refresh();

        expect($user->name)->toBe('Updated Name')
            ->and($user->email)->toBe('updated@example.com');
    });

    it('applies a partial update without touching untouched fields', function (): void {
        $user = User::factory()->create([
            'name' => 'Original',
            'email' => 'original@example.com',
        ]);

        $this->putJson(sprintf('%s/%s', $this->baseUrl, $user->id), ['name' => 'Changed'])
            ->assertOk();

        $user->refresh();

        expect($user->name)->toBe('Changed')
            ->and($user->email)->toBe('original@example.com');
    });

    it('rejects an email already used by another user', function (): void {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->putJson(sprintf('%s/%s', $this->baseUrl, $user->id), ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });

    it('allows a user to keep its own email on update', function (): void {
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->putJson(sprintf('%s/%s', $this->baseUrl, $user->id), [
            'name' => 'Renamed',
            'email' => 'mine@example.com',
        ])->assertOk();
    });

    it('deletes a user when authenticated', function (): void {
        $user = User::factory()->create();

        $response = $this->deleteJson(sprintf('%s/%s', $this->baseUrl, $user->id));

        $response->assertNoContent();

        expect(User::query()->find($user->id))->toBeNull();
    });

    it('denies access to unauthenticated users', function (): void {
        auth()->logout();

        $this->getJson($this->baseUrl)->assertUnauthorized();
        $this->postJson($this->baseUrl)->assertUnauthorized();
        $this->getJson($this->baseUrl.'/1')->assertUnauthorized();
        $this->putJson($this->baseUrl.'/1')->assertUnauthorized();
        $this->deleteJson($this->baseUrl.'/1')->assertUnauthorized();
    });

    it('forbids a non-admin from admin-only actions', function (): void {
        $this->actingAs(User::factory()->create());
        $other = User::factory()->create();

        $this->getJson($this->baseUrl)->assertForbidden();
        $this->postJson($this->baseUrl, [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => '!Password123',
        ])->assertForbidden();
        $this->getJson(sprintf('%s/%s', $this->baseUrl, $other->id))->assertForbidden();
        $this->putJson(sprintf('%s/%s', $this->baseUrl, $other->id), ['name' => 'Nope'])->assertForbidden();
        $this->deleteJson(sprintf('%s/%s', $this->baseUrl, $other->id))->assertForbidden();
    });

    it('lets a non-admin view and update only their own record', function (): void {
        $user = User::factory()->create(['name' => 'Self']);
        $this->actingAs($user);

        $this->getJson(sprintf('%s/%s', $this->baseUrl, $user->id))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->putJson(sprintf('%s/%s', $this->baseUrl, $user->id), ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    });

    it('forbids an admin from deleting their own account', function (): void {
        $this->deleteJson(sprintf('%s/%s', $this->baseUrl, auth()->id()))
            ->assertForbidden();
    });

    it('returns a problem+json body for a forbidden request', function (): void {
        $this->actingAs(User::factory()->create());

        $this->getJson($this->baseUrl)
            ->assertForbidden()
            ->assertHeader('content-type', 'application/problem+json')
            ->assertJsonPath('status', 403)
            ->assertJsonPath('title', 'Forbidden');
    });
});
