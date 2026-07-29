<?php

declare(strict_types=1);

use App\Data\Filters\Models\UserFilters;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

covers([UserRepository::class, UserFilters::class]);

beforeEach(fn (): UserRepository => $this->repository = new UserRepository(new User));

describe('Repository | User', function (): void {
    it('finds a user by ID', function (): void {
        $user = User::factory()->create();
        $foundUser = $this->repository->find($user->id);
        assert($foundUser instanceof User);

        expect($foundUser->id)->toBe($user->id);
    });

    it('retrieves all users', function (): void {
        User::factory(5)->create();

        expect($this->repository->findAll())->toHaveCount(5);
    });

    it('retrieves paginated users', function (): void {
        User::factory(15)->create();

        $paginator = $this->repository->findAll(perPage: 10);
        assert($paginator instanceof LengthAwarePaginator);

        expect($paginator)
            ->and($paginator->items())->toHaveCount(10)
            ->and($paginator->currentPage())->toBe(1);
    });

    it('filters users by IDs', function (): void {
        $users = User::factory(3)->create();

        /** @var list<int> $ids */
        $ids = $users->take(2)->pluck('id')->toArray();
        $filters = new UserFilters(ids: $ids);

        expect($this->repository->getFilteredQuery($filters)->count())->toBe(2);
    });

    it('sorts users correctly', function (array $order): void {
        $users = new Collection([
            User::factory()->create(['name' => 'Charlie']),
            User::factory()->create(['name' => 'Alice']),
            User::factory()->create(['name' => 'Bob']),
        ]);

        /** @var array<int, string>|array<string, 'asc'> $order */
        expect($this->repository->getFilteredQuery(new UserFilters, $order)->pluck('name')->toArray())
            ->toBe($users->sortBy('name')->pluck('name')->toArray());
    })->with([
        'column => direction' => [['name' => 'asc']],
        'bare column name' => [['name']],
    ]);

    it('filters users correctly using findBy without pagination', function (): void {
        $user = User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $filteredUsers = $this->repository->findBy(new UserFilters(ids: [$user->id]));

        expect($filteredUsers)->toBeInstanceOf(Collection::class)
            ->and($filteredUsers)->toHaveCount(1);

        $foundUser = $filteredUsers->first();
        assert($foundUser instanceof User);

        expect($foundUser->id)->toBe($user->id);
    });

    it('returns paginated results when perPage is provided in findBy', function (): void {
        User::factory(15)->create();

        $paginator = $this->repository->findBy(filters: new UserFilters, perPage: 10);
        assert($paginator instanceof LengthAwarePaginator);

        expect($paginator)
            ->and($paginator->items())->toHaveCount(10)
            ->and($paginator->currentPage())->toBe(1);
    });
});
