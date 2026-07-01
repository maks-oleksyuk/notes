<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\User\UserController;
use App\Models\User;

covers(UserController::class);

beforeEach(function (): void {
    $this->baseUrl = '/api/v1/users';
    $this->actingAs(User::factory()->create());
});

describe('API | V1 | Actions | Users', function (): void {
    it('returns paginated user list for authenticated user', function (): void {
        User::factory()->makeMany(3);

        $response = $this->getJson($this->baseUrl);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email']],
                'links',
                'meta',
            ]);
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
});
