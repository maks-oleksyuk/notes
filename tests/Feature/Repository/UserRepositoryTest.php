<?php

declare(strict_types=1);

use App\Data\Filters\Models\UserFilters;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->repository = new UserRepository(new User));

it('finds a user by ID', function () {
    $user = User::factory()->create();
    $foundUser = $this->repository->find($user->id);

    expect($foundUser)->toBeInstanceOf(User::class)
        ->and($foundUser->id)->toBe($user->id);
});

it('retrieves all users', function () {
    User::factory(5)->create();

    expect($this->repository->findAll())->toHaveCount(5);
});

it('retrieves paginated users', function () {
    User::factory(15)->create();

    expect($this->repository->findAll(perPage: 10))->toHaveCount(10);
});

it('filters users by IDs', function () {
    $users = User::factory(3)->create();
    $filters = new UserFilters(ids: $users->take(2)->pluck('id')->toArray());

    expect($this->repository->getFilteredQuery($filters)->count())->toBe(2);
});

it('sorts users correctly', function () {
    $users = collect([
        User::factory()->create(['name' => 'Charlie']),
        User::factory()->create(['name' => 'Alice']),
        User::factory()->create(['name' => 'Bob']),
    ]);

    foreach ([['name' => 'asc'], ['name']] as $order) {
        expect($this->repository->getFilteredQuery(new UserFilters, $order)->pluck('name')->toArray())
            ->toBe($users->sortBy('name')->pluck('name')->toArray());
    }
});

it('filters users correctly', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Bob']);

    $filteredUsers = $this->repository->findBy(new UserFilters(ids: [$user->id]));

    expect($filteredUsers)->toHaveCount(1)
        ->and($filteredUsers->first()->id)->toBe($user->id);
});
