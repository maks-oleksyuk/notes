<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @implements BaseRepositoryInterface<User>
 */
final readonly class UserRepository implements BaseRepositoryInterface
{
    public function all(): Collection
    {
        return User::all();
    }

    public function find(int|string $id): User
    {
        return User::query()->findOrFail($id);
    }
}
