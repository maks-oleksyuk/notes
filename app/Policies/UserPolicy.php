<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::SuperAdmin) || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::SuperAdmin) || $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::SuperAdmin) && ! $user->is($model);
    }
}
